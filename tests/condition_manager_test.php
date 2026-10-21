<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_dynamic_cohorts;

use core\event\user_created;
use tool_dynamic_cohorts\event\condition_created;
use tool_dynamic_cohorts\event\condition_deleted;
use tool_dynamic_cohorts\event\condition_updated;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_last_login;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for condition manager class.
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_dynamic_cohorts\condition_manager::class)]
final class condition_manager_test extends \advanced_testcase {
    /**
     * Test all conditions.
     */
    public function test_get_all_conditions(): void {
        $conditions = condition_manager::get_all_conditions();
        $this->assertIsArray($conditions);
        $this->assertNotEmpty($conditions);

        foreach ($conditions as $condition) {
            $this->assertFalse(is_null($condition));
            $this->assertTrue(is_subclass_of($condition, condition_base::class));
            $this->assertFalse($condition->is_broken());
        }
    }

    /** @var string Condition class used where a real, cheaply configured condition is needed. */
    private const LAST_LOGIN = 'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_last_login';

    /**
     * Build one entry of the rule form's conditionjson for a last-login condition.
     *
     * @param int $id Condition id, 0 for a new one.
     * @param int $sortorder Sort order.
     * @param array $configdata Config data, stored JSON-encoded as the form does.
     * @return array
     */
    private function last_login_entry(int $id, int $sortorder, array $configdata): array {
        return ['id' => $id, 'classname' => self::LAST_LOGIN, 'sortorder' => $sortorder, 'configdata' => json_encode($configdata)];
    }

    /**
     * Build rule form data that marks the conditions as changed.
     *
     * @param rule $rule The rule being saved.
     * @param array $conditionjson Entries for the conditionjson field.
     * @return \stdClass
     */
    private function changed_conditions_formdata(rule $rule, array $conditionjson): \stdClass {
        return (object) ['id' => $rule->get('id'), 'name' => 'Test', 'cohortid' => $rule->get('cohortid'),
            'description' => '', 'conditionjson' => json_encode($conditionjson), 'isconditionschanged' => true,
            'bulkprocessing' => 1];
    }

    /**
     * Read a rule's stored conditions as id => decoded config data.
     *
     * @param rule $rule The rule.
     * @return array
     */
    private function stored_configdata(rule $rule): array {
        $result = [];
        foreach (condition::get_records(['ruleid' => $rule->get('id')]) as $condition) {
            $result[(int) $condition->get('id')] = json_decode($condition->get('configdata'), true);
        }
        return $result;
    }

    /**
     * Test processing condition form
     */
    public function test_process_form(): void {
        global $DB;

        $this->resetAfterTest();
        $cohort = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object)['name' => 'Test rule', 'cohortid' => $cohort->id]);
        $rule->save();

        // Creating rule without conditions.
        $formdata = ['name' => 'Test', 'cohortid' => $cohort->id, 'description' => '',
            'conditionjson' => '', 'bulkprocessing' => 1];

        $eventsink = $this->redirectEvents();

        condition_manager::process_form($rule, (object)$formdata);

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_created
                || $event instanceof condition_updated
                || $event instanceof condition_deleted;
        });

        $this->assertEmpty($events);
        $eventsink->clear();
        $this->assertEquals(1, $DB->count_records(rule::TABLE));
        $this->assertCount(0, $rule->get_condition_records());

        // Updating the rule with 3 new conditions, but flag isconditionschanged is not set.
        $conditionjson = [
            $this->last_login_entry(0, 0, ['operator' => user_last_login::OPERATOR_EVER]),
            $this->last_login_entry(0, 1, ['operator' => user_last_login::OPERATOR_NEVER]),
            $this->last_login_entry(0, 2, ['operator' => user_last_login::OPERATOR_AFTER, 'time' => 1000]),
        ];

        $formdata = $this->changed_conditions_formdata($rule, $conditionjson);
        unset($formdata->isconditionschanged);

        condition_manager::process_form($rule, $formdata);
        $this->assertCount(0, $rule->get_condition_records());

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_created
                || $event instanceof condition_updated
                || $event instanceof condition_deleted;
        });
        $this->assertEmpty($events);
        $eventsink->clear();

        // Updating the rule with 3 new conditions. Expecting 3 new conditions to be created.
        condition_manager::process_form($rule, $this->changed_conditions_formdata($rule, $conditionjson));

        $this->assertCount(3, $rule->get_condition_records());
        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_created;
        });
        $this->assertCount(3, $events);
        $eventsink->clear();

        $operators = array_column($this->stored_configdata($rule), 'operator');
        sort($operators);
        $this->assertSame(
            [user_last_login::OPERATOR_EVER, user_last_login::OPERATOR_NEVER, user_last_login::OPERATOR_AFTER],
            $operators
        );

        // Updating the rule with 1 new condition, 1 deleted condition (sortorder 1) and two updated
        // conditions (operator changed). Expecting 1 new condition, 2 updated and 1 deleted.
        $conditionjson = [];
        $kept = [];
        $deleted = 0;
        foreach ($rule->get_condition_records() as $condition) {
            if ($condition->get('sortorder') == 1) {
                $deleted = (int) $condition->get('id');
                continue;
            }
            $kept[] = (int) $condition->get('id');
            $conditionjson[] = $this->last_login_entry(
                (int) $condition->get('id'),
                (int) $condition->get('sortorder'),
                ['operator' => user_last_login::OPERATOR_NEVER]
            );
        }
        $conditionjson[] = $this->last_login_entry(0, 2, ['operator' => user_last_login::OPERATOR_EVER]);

        condition_manager::process_form($rule, $this->changed_conditions_formdata($rule, $conditionjson));

        $this->assertCount(3, $rule->get_condition_records());

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_created;
        });
        $this->assertCount(1, $events);

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_updated;
        });
        $this->assertCount(2, $events);

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_deleted;
        });
        $this->assertCount(1, $events);
        $eventsink->clear();

        $stored = $this->stored_configdata($rule);
        $this->assertArrayNotHasKey($deleted, $stored);
        foreach ($kept as $id) {
            $this->assertSame(['operator' => user_last_login::OPERATOR_NEVER], $stored[$id]);
        }
        $new = array_diff_key($stored, array_flip($kept));
        $this->assertSame([['operator' => user_last_login::OPERATOR_EVER]], array_values($new));
    }

    /**
     * A condition id belonging to another rule is refused, and that rule's condition is untouched.
     */
    public function test_process_form_refuses_a_condition_of_another_rule(): void {
        $this->resetAfterTest();

        $rulea = new rule(0, (object)['name' => 'Rule A', 'cohortid' => $this->getDataGenerator()->create_cohort()->id]);
        $rulea->save();
        $ruleb = new rule(0, (object)['name' => 'Rule B', 'cohortid' => $this->getDataGenerator()->create_cohort()->id]);
        $ruleb->save();

        $entry = $this->last_login_entry(0, 0, ['operator' => user_last_login::OPERATOR_EVER]);
        condition_manager::process_form($ruleb, $this->changed_conditions_formdata($ruleb, [$entry]));
        $conditionb = array_values($ruleb->get_condition_records())[0];

        // The control: rule B's own form may rewrite the same condition.
        $own = $this->last_login_entry((int) $conditionb->get('id'), 0, ['operator' => user_last_login::OPERATOR_NEVER]);
        condition_manager::process_form($ruleb, $this->changed_conditions_formdata($ruleb, [$own]));
        $this->assertSame(
            ['operator' => user_last_login::OPERATOR_NEVER],
            $this->stored_configdata($ruleb)[(int) $conditionb->get('id')]
        );

        $foreign = $this->last_login_entry((int) $conditionb->get('id'), 0, ['operator' => user_last_login::OPERATOR_AFTER,
            'time' => 1000]);
        try {
            condition_manager::process_form($rulea, $this->changed_conditions_formdata($rulea, [$foreign]));
            $this->fail('A condition of another rule was accepted.');
        } catch (\moodle_exception $exception) {
            $this->assertStringContainsString('does not belong to this rule', $exception->getMessage());
        }

        $after = condition::get_record(['id' => $conditionb->get('id')]);
        $this->assertEquals($ruleb->get('id'), $after->get('ruleid'));
        $this->assertSame(['operator' => user_last_login::OPERATOR_NEVER], json_decode($after->get('configdata'), true));
        $this->assertCount(0, $rulea->get_condition_records());
    }

    /**
     * A non-numeric submitted id is the id that was checked (0), so the condition is created as new.
     */
    public function test_process_form_uses_the_checked_condition_id(): void {
        $this->resetAfterTest();

        $rule = new rule(0, (object)['name' => 'Rule', 'cohortid' => $this->getDataGenerator()->create_cohort()->id]);
        $rule->save();

        $entry = $this->last_login_entry(0, 0, ['operator' => user_last_login::OPERATOR_EVER]);
        $entry['id'] = 'abc';
        condition_manager::process_form($rule, $this->changed_conditions_formdata($rule, [$entry]));

        $this->assertCount(1, $rule->get_condition_records());
    }

    /**
     * Data provider for test_process_form_refuses_invalid_condition.
     *
     * @return array
     */
    public static function invalid_condition_provider(): array {
        $lastlogin = self::LAST_LOGIN;
        return [
            'unknown class' => ['tool_dynamic_cohorts\not_a_condition', '{"operator":1}', 'Unknown condition'],
            'config data is not an object' => [$lastlogin, '"x"', 'Invalid config data'],
            'config data fails the form validation' => [$lastlogin, '{"operator":5}', 'Invalid config data'],
            'a getter raises a TypeError' => [$lastlogin, '{"operator":"abc"}', 'Invalid config data'],
            'only get_sql raises a TypeError' => [
                $lastlogin,
                '{"operator":5,"period_value":1,"period_type":["day"]}',
                'Invalid config data',
            ],
        ];
    }

    /**
     * Submitted conditions are validated on save, not only in the condition modal.
     *
     * @param string $classname Submitted class name.
     * @param string $configdata Submitted config data.
     * @param string $message Expected part of the exception message.
     */
    #[DataProvider('invalid_condition_provider')]
    public function test_process_form_refuses_invalid_condition(string $classname, string $configdata, string $message): void {
        $this->resetAfterTest();

        $rule = new rule(0, (object)['name' => 'Rule', 'cohortid' => $this->getDataGenerator()->create_cohort()->id]);
        $rule->save();
        $entry = ['id' => 0, 'classname' => $classname, 'sortorder' => 0, 'configdata' => $configdata];

        try {
            condition_manager::process_form($rule, $this->changed_conditions_formdata($rule, [$entry]));
            $this->fail('An invalid condition was accepted.');
        } catch (\moodle_exception $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->assertCount(0, $rule->get_condition_records());
    }

    /**
     * A stored condition sent back unchanged is saved even when it no longer validates.
     */
    public function test_process_form_keeps_an_unchanged_stored_condition(): void {
        $this->resetAfterTest();

        $rule = new rule(0, (object)['name' => 'Rule', 'cohortid' => $this->getDataGenerator()->create_cohort()->id]);
        $rule->save();

        // As left behind by an uninstalled plugin: its class is gone.
        $orphan = new condition(0, (object)['ruleid' => $rule->get('id'), 'classname' => 'local_gone\condition',
            'sortorder' => 0, 'configdata' => '{"a":1}']);
        $orphan->save();

        $conditionjson = [
            ['id' => (int) $orphan->get('id'), 'classname' => 'local_gone\condition', 'sortorder' => 0,
                'configdata' => '{"a":1}'],
            $this->last_login_entry(0, 1, ['operator' => user_last_login::OPERATOR_EVER]),
        ];
        condition_manager::process_form($rule, $this->changed_conditions_formdata($rule, $conditionjson));

        $this->assertCount(2, $rule->get_condition_records());

        // The control: the same stored condition with changed config data is new input again.
        $conditionjson[0]['configdata'] = '{"a":2}';
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Unknown condition');
        condition_manager::process_form($rule, $this->changed_conditions_formdata($rule, $conditionjson));
    }

    /**
     * Test delete_conditions.
     */
    public function test_delete_conditions(): void {
        global $DB;

        $this->resetAfterTest();

        $this->assertSame(0, $DB->count_records(condition::TABLE));

        $cohort = $this->getDataGenerator()->create_cohort(['component' => 'tool_dynamic_cohorts']);
        $rule = new rule(0, (object)['name' => 'Test rule', 'cohortid' => $cohort->id]);
        $rule->save();

        $condition1 = new condition(0, (object)['ruleid' => $rule->get('id'), 'classname' => 'test', 'sortorder' => 0]);
        $condition1->save();

        $condition2 = new condition(0, (object)['ruleid' => $rule->get('id'), 'classname' => 'test2', 'sortorder' => 1]);
        $condition2->save();

        $this->assertSame(2, $DB->count_records(condition::TABLE));

        $eventsink = $this->redirectEvents();

        condition_manager::delete_conditions([$condition1, $condition2, $rule, $cohort]);
        $this->assertSame(0, $DB->count_records(condition::TABLE));

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_deleted;
        });

        $this->assertCount(2, $events);
        $eventsink->clear();
    }

    /**
     * Test getting conditions for a given event.
     */
    public function test_get_conditions_with_event(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        $event = user_created::create_from_userid($user->id);
        $conditions = condition_manager::get_conditions_with_event($event);

        $this->assertArrayHasKey('tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_custom_profile', $conditions);
        $this->assertArrayHasKey('tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile', $conditions);
    }

    /**
     * Basic test of building SQL data.
     */
    public function test_build_sql_data(): void {
        $this->resetAfterTest();

        $this->getDataGenerator()->create_user(['username' => 'user1username']);
        $this->getDataGenerator()->create_user(['username' => 'user2username']);
        $this->getDataGenerator()->create_user(['username' => 'test']);

        $cohort = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object)['name' => 'Test rule 1', 'cohortid' => $cohort->id,
            'operator' => rule_manager::CONDITIONS_OPERATOR_OR]);
        $rule->save();

        $conditions = [];

        $condition = user_profile::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 1]);
        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => condition_base::TEXT_IS_EQUAL_TO,
            'username_value' => 'user1username',
        ]);
        $condition->get_record()->save();
        $conditions[] = $condition->get_record();

        $condition = user_profile::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 1]);
        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => condition_base::TEXT_IS_EQUAL_TO,
            'username_value' => 'user2username',
        ]);
        $condition->get_record()->save();
        $conditions[] = $condition->get_record();

        $sql = condition_manager::build_sql_data($conditions);
        $this->assertEquals('', $sql->get_join());
        $this->assertStringNotContainsString('OR', $sql->get_where());
        $this->assertStringContainsString('AND ( u.deleted = 0)', $sql->get_where());
        $this->assertTrue(in_array('user1username', $sql->get_params()));
        $this->assertTrue(in_array('user2username', $sql->get_params()));
        $this->assertStringNotContainsString('AND ( u.id = ', $sql->get_where());
        $this->assertFalse(in_array(777, $sql->get_params()));

        $sql = condition_manager::build_sql_data($conditions, rule_manager::CONDITIONS_OPERATOR_OR);
        $this->assertEquals('', $sql->get_join());
        $this->assertStringContainsString('OR', $sql->get_where());
        $this->assertStringContainsString('AND ( u.deleted = 0)', $sql->get_where());
        $this->assertTrue(in_array('user1username', $sql->get_params()));
        $this->assertTrue(in_array('user2username', $sql->get_params()));
        $this->assertStringNotContainsString('AND ( u.id = ', $sql->get_where());
        $this->assertFalse(in_array(777, $sql->get_params()));

        $sql = condition_manager::build_sql_data($conditions, rule_manager::CONDITIONS_OPERATOR_OR, 777);
        $this->assertEquals('', $sql->get_join());
        $this->assertStringContainsString('OR', $sql->get_where());
        $this->assertStringContainsString('AND ( u.deleted = 0)', $sql->get_where());
        $this->assertStringContainsString('AND ( u.id = ', $sql->get_where());
        $this->assertTrue(in_array('user1username', $sql->get_params()));
        $this->assertTrue(in_array('user2username', $sql->get_params()));
        $this->assertTrue(in_array(777, $sql->get_params()));
    }

    /**
     * Test that generated SQL should exclude deleted users.
     */
    public function test_should_exclude_deleted_users(): void {
        global $DB;

        $this->resetAfterTest();

        $this->getDataGenerator()->create_user(['username' => 'user1username', 'auth' => 'lti']);
        $this->getDataGenerator()->create_user(['username' => 'user2username', 'auth' => 'lti']);
        $usertodeleted = $this->getDataGenerator()->create_user(['username' => 'user3username', 'auth' => 'lti']);
        $this->getDataGenerator()->create_user(['username' => 'test']);

        $cohort = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object)['name' => 'Test rule 1', 'cohortid' => $cohort->id,
            'operator' => rule_manager::CONDITIONS_OPERATOR_OR]);
        $rule->save();

        $conditions = [];
        $condition = user_profile::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 1]);
        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => condition_base::TEXT_CONTAINS,
            'username_value' => 'username',
        ]);
        $condition->get_record()->save();
        $conditions[] = $condition->get_record();

        $condition = user_profile::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 1]);
        $condition->set_config_data([
            'profilefield' => 'auth',
            'auth_operator' => condition_base::TEXT_IS_EQUAL_TO,
            'auth_value' => 'lti',
        ]);
        $condition->get_record()->save();
        $conditions[] = $condition->get_record();

        $sqldataand = condition_manager::build_sql_data($conditions);
        $sqldataor = condition_manager::build_sql_data($conditions, rule_manager::CONDITIONS_OPERATOR_OR);

        $basesql = "SELECT DISTINCT u.id FROM {user} u ";
        $sqland = $basesql . $sqldataand->get_join() . ' WHERE ' . $sqldataand->get_where();
        $sqlor = $basesql . $sqldataor->get_join() . ' WHERE ' . $sqldataor->get_where();

        $this->assertCount(3, $DB->get_records_sql($sqland, $sqldataand->get_params()));
        $this->assertCount(3, $DB->get_records_sql($sqlor, $sqldataor->get_params()));
        $this->assertArrayHasKey($usertodeleted->id, $DB->get_records_sql($sqland, $sqldataand->get_params()));
        $this->assertArrayHasKey($usertodeleted->id, $DB->get_records_sql($sqlor, $sqldataor->get_params()));

        delete_user($usertodeleted);

        $this->assertCount(2, $DB->get_records_sql($sqland, $sqldataand->get_params()));
        $this->assertCount(2, $DB->get_records_sql($sqlor, $sqldataor->get_params()));
        $this->assertArrayNotHasKey($usertodeleted->id, $DB->get_records_sql($sqland, $sqldataand->get_params()));
        $this->assertArrayNotHasKey($usertodeleted->id, $DB->get_records_sql($sqlor, $sqldataor->get_params()));
    }
}

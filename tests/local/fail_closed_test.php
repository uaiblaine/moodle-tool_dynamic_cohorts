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

namespace tool_dynamic_cohorts\local;

use PHPUnit\Framework\Attributes\CoversClass;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\event\matching_failed;
use tool_dynamic_cohorts\fixtures\hostile_condition;
use tool_dynamic_cohorts\fixtures\testable_observer;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_role;
use tool_dynamic_cohorts\observer;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\rule_manager;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/hostile_condition.php');
require_once(__DIR__ . '/../fixtures/testable_observer.php');

/**
 * Malformed stored conditions and misbehaving condition code fail closed.
 *
 * A condition that cannot be evaluated must break its rule, not match everyone, and a \TypeError from
 * condition code must not escape into the request that fired an event: core catches only \Exception
 * from observers.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(user_role::class)]
#[CoversClass(rule_manager::class)]
#[CoversClass(observer::class)]
#[CoversClass(user_profile::class)]
final class fail_closed_test extends \advanced_testcase {
    /**
     * Reset after each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * An enabled real-time rule on a new cohort holding one condition.
     *
     * @param string $classname Condition class name.
     * @param array $configdata Config data.
     * @return rule
     */
    private function rule_with(string $classname, array $configdata): rule {
        $cohort = $this->getDataGenerator()->create_cohort();
        $rule = new rule(0, (object) ['name' => 'Rule', 'enabled' => 1, 'realtime' => 1, 'cohortid' => $cohort->id]);
        $rule->save();

        $condition = condition_base::get_instance(0, (object) ['classname' => $classname]);
        $condition->set_config_data($configdata);
        $record = $condition->get_record();
        $record->set('ruleid', $rule->get('id'));
        $record->set('sortorder', 0);
        $record->save();

        return $rule;
    }

    /**
     * A stored role condition with a level get_sql() has no branch for is broken, not site-wide.
     */
    public function test_user_role_unknown_context_level_is_broken(): void {
        $roleid = (int) $this->getDataGenerator()->create_role();
        $condition = condition_base::get_instance(0, (object) ['classname' => user_role::class]);

        $condition->set_config_data(['operator' => user_role::OPERATOR_HAVE_ROLE, 'roleid' => $roleid, 'contextlevel' => 70]);
        $this->assertTrue($condition->is_broken());

        // The control: the same role at the system level.
        $condition->set_config_data(['operator' => user_role::OPERATOR_HAVE_ROLE, 'roleid' => $roleid, 'contextlevel' => 10]);
        $this->assertFalse($condition->is_broken());
    }

    /**
     * A \TypeError from a condition's get_sql() breaks the rule while listing its users.
     */
    public function test_matching_users_catches_a_type_error(): void {
        $user = $this->getDataGenerator()->create_user();
        $rule = $this->rule_with(hostile_condition::class, ['throwsql' => 1, 'userids' => [(int) $user->id]]);
        $sink = $this->redirectEvents();

        $this->assertSame([], rule_manager::get_matching_users($rule));
        $this->assertTrue(rule::get_record(['id' => $rule->get('id')])->is_broken());
        $this->assertCount(1, array_filter($sink->get_events(), fn($event) => $event instanceof matching_failed));

        // The control: the same rule without the failure lists its user.
        $control = $this->rule_with(hostile_condition::class, ['userids' => [(int) $user->id]]);
        $this->assertCount(1, rule_manager::get_matching_users($control));
    }

    /**
     * The same while counting them.
     */
    public function test_matching_users_count_catches_a_type_error(): void {
        $user = $this->getDataGenerator()->create_user();
        $rule = $this->rule_with(hostile_condition::class, ['throwsql' => 1, 'userids' => [(int) $user->id]]);

        $this->assertSame(0, (int) rule_manager::get_matching_users_count($rule));
        $this->assertTrue(rule::get_record(['id' => $rule->get('id')])->is_broken());

        $control = $this->rule_with(hostile_condition::class, ['userids' => [(int) $user->id]]);
        $this->assertSame(1, (int) rule_manager::get_matching_users_count($control));
    }

    /**
     * A \TypeError from is_relevant_event() does not escape the observer; the rule is processed.
     */
    public function test_observer_catches_a_type_error_in_relevance(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $rule = $this->rule_with(hostile_condition::class, ['throwrelevant' => 1, 'userids' => [(int) $user->id]]);
        $event = \core\event\user_loggedin::create([
            'userid' => $user->id,
            'objectid' => $user->id,
            'other' => ['username' => $user->username],
        ]);

        testable_observer::process_with($event, [hostile_condition::get_instance()]);

        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $rule->get('cohortid'), 'userid' => $user->id]));
    }

    /**
     * A field condition submitted without a field is refused at once, without reading missing keys.
     */
    public function test_field_validation_without_a_field_returns_early(): void {
        $this->setAdminUser();
        $condition = condition_base::get_instance(0, (object) ['classname' => user_profile::class]);

        $warnings = [];
        set_error_handler(function (int $errno, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });
        try {
            $errors = $condition->config_form_validate([]);
        } finally {
            restore_error_handler();
        }

        $this->assertArrayHasKey('fieldgroup', $errors);
        $this->assertSame([], $warnings);
    }
}

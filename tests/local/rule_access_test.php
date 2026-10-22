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

use core\context;
use core_reportbuilder\system_report_factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_dynamic_cohorts\cohort_manager;
use tool_dynamic_cohorts\condition;
use tool_dynamic_cohorts\event\condition_deleted;
use tool_dynamic_cohorts\event\rule_created;
use tool_dynamic_cohorts\external\condition_form;
use tool_dynamic_cohorts\external\matching_users as matching_users_external;
use tool_dynamic_cohorts\external\rule_conditions;
use tool_dynamic_cohorts\external\rules;
use tool_dynamic_cohorts\reportbuilder\local\systemreports\matching_users;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\rule_form;
use tool_dynamic_cohorts\rule_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/dynamic_cohorts/lib.php');

/**
 * Capability matrix for managing rules in a course category.
 *
 * A category manager holds tool/dynamic_cohorts:manage and moodle/cohort:assign in category A
 * only. A1 is a subcategory of A, A2 a subcategory of A where the plugin capability is
 * prohibited, B a sibling category; the system context is out of reach.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(rule_access::class)]
#[CoversClass(rule_manager::class)]
#[CoversClass(cohort_manager::class)]
#[CoversClass(rule_form::class)]
#[CoversClass(rules::class)]
#[CoversClass(rule_conditions::class)]
#[CoversClass(matching_users_external::class)]
#[CoversClass(condition_form::class)]
#[CoversClass(matching_users::class)]
final class rule_access_test extends \advanced_testcase {
    /** @var array Contexts by key: a, a1, a2, b, system. */
    private array $contexts = [];

    /** @var array Cohort ids by key, each with a rule pointing at it. */
    private array $cohorts = [];

    /** @var array Rules by the key of their cohort. */
    private array $rules = [];

    /** @var array Cohort ids by key, with no rule yet, for creating one. */
    private array $freecohorts = [];

    /** @var int The category manager. */
    private int $manager;

    /** @var int Holds the plugin capability in A but not moodle/cohort:assign. */
    private int $noassign;

    /**
     * Build the categories, cohorts, rules and users.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $a = $generator->create_category();
        $a1 = $generator->create_category(['parent' => $a->id]);
        $a2 = $generator->create_category(['parent' => $a->id]);
        $b = $generator->create_category();
        $this->contexts = [
            'a' => \core\context\coursecat::instance($a->id),
            'a1' => \core\context\coursecat::instance($a1->id),
            'a2' => \core\context\coursecat::instance($a2->id),
            'b' => \core\context\coursecat::instance($b->id),
            'system' => \core\context\system::instance(),
        ];

        foreach ($this->contexts as $key => $context) {
            $cohort = $generator->create_cohort(['contextid' => $context->id]);
            $this->cohorts[$key] = (int) $cohort->id;
            $this->freecohorts[$key] = (int) $generator->create_cohort(['contextid' => $context->id])->id;

            $rule = new rule(0, (object) ['name' => 'Rule ' . $key, 'cohortid' => $cohort->id]);
            $rule->save();
            $this->rules[$key] = $rule;
        }

        $roleid = $generator->create_role();
        assign_capability('tool/dynamic_cohorts:manage', CAP_ALLOW, $roleid, $this->contexts['a']->id);
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $this->contexts['a']->id);
        assign_capability('tool/dynamic_cohorts:manage', CAP_PROHIBIT, $roleid, $this->contexts['a2']->id);
        $this->manager = (int) $generator->create_user()->id;
        role_assign($roleid, $this->manager, $this->contexts['a']->id);

        $noassignroleid = $generator->create_role();
        assign_capability('tool/dynamic_cohorts:manage', CAP_ALLOW, $noassignroleid, $this->contexts['a']->id);
        $this->noassign = (int) $generator->create_user()->id;
        role_assign($noassignroleid, $this->noassign, $this->contexts['a']->id);
    }

    /**
     * Rule form data for rule_manager::process_form().
     *
     * @param int $cohortid Cohort the rule fills.
     * @param int $id Rule id, 0 for a new rule.
     * @return \stdClass
     */
    private function rule_formdata(int $cohortid, int $id = 0): \stdClass {
        return (object) ['id' => $id, 'name' => 'Test', 'cohortid' => $cohortid, 'description' => '',
            'conditionjson' => '', 'bulkprocessing' => 0, 'realtime' => 0,
            'operator' => rule_manager::CONDITIONS_OPERATOR_AND];
    }

    /**
     * Assert that a callable is refused for lack of a capability.
     *
     * @param callable $callable The operation.
     */
    private function assert_refused(callable $callable): void {
        try {
            $callable();
            $this->fail('The operation was allowed.');
        } catch (\required_capability_exception $exception) {
            $this->assertInstanceOf(\required_capability_exception::class, $exception);
        }
    }

    /**
     * Who may manage rules where.
     */
    public function test_can_manage(): void {
        $this->setUser($this->manager);
        $this->assertTrue(rule_access::can_manage($this->contexts['a']));
        $this->assertTrue(rule_access::can_manage($this->contexts['a1']));
        $this->assertFalse(rule_access::can_manage($this->contexts['a2']));
        $this->assertFalse(rule_access::can_manage($this->contexts['b']));
        $this->assertFalse(rule_access::can_manage($this->contexts['system']));

        // Without moodle/cohort:assign the plugin capability alone is not enough.
        $this->setUser($this->noassign);
        $this->assertTrue(has_capability('tool/dynamic_cohorts:manage', $this->contexts['a']));
        $this->assertFalse(rule_access::can_manage($this->contexts['a']));
    }

    /**
     * Data provider: every web service that acts on one rule.
     *
     * @return array
     */
    public static function rule_service_provider(): array {
        return [
            'get_conditions' => ['get_conditions'],
            'get_total' => ['get_total'],
            'toggle_status' => ['toggle_status'],
            'delete_rules' => ['delete_rules'],
        ];
    }

    /**
     * Call one rule web service.
     *
     * @param string $service Key from rule_service_provider().
     * @param int $ruleid Rule id.
     */
    private function call_rule_service(string $service, int $ruleid): void {
        match ($service) {
            'get_conditions' => rule_conditions::get_conditions($ruleid),
            'get_total' => matching_users_external::get_total($ruleid),
            'toggle_status' => rules::toggle_status($ruleid),
            'delete_rules' => rules::delete_rules([$ruleid]),
        };
    }

    /**
     * Each rule web service is decided in the rule's cohort context.
     *
     * @param string $service Key from rule_service_provider().
     */
    #[DataProvider('rule_service_provider')]
    public function test_rule_services_follow_the_cohort_context(string $service): void {
        $this->setUser($this->manager);

        foreach (['b', 'system', 'a2'] as $key) {
            $this->assert_refused(fn() => $this->call_rule_service($service, (int) $this->rules[$key]->get('id')));
        }

        // A rule id that does not exist is refused like one out of reach, not reported as missing.
        $this->assert_refused(fn() => $this->call_rule_service($service, 999999));

        // Not without moodle/cohort:assign, where the plugin capability alone is held.
        $this->setUser($this->noassign);
        $this->assert_refused(fn() => $this->call_rule_service($service, (int) $this->rules['a1']->get('id')));

        // The controls: allowed in A and in its subcategory A1.
        $this->setUser($this->manager);
        $this->call_rule_service($service, (int) $this->rules['a1']->get('id'));
        $this->call_rule_service($service, (int) $this->rules['a']->get('id'));
    }

    /**
     * One refused rule leaves every rule of the list in place.
     */
    public function test_delete_rules_is_one_operation(): void {
        $this->setUser($this->manager);

        $ids = [(int) $this->rules['a']->get('id'), (int) $this->rules['b']->get('id')];
        $this->assert_refused(fn() => rules::delete_rules($ids));
        $this->assertTrue(rule::record_exists($ids[0]));
        $this->assertTrue(rule::record_exists($ids[1]));
    }

    /**
     * Saving a rule is decided on the cohort it is pointed at.
     */
    public function test_saving_a_rule_needs_the_new_cohort(): void {
        $this->setUser($this->manager);

        $this->assert_refused(fn() => rule_manager::process_form($this->rule_formdata($this->freecohorts['b'])));
        $this->assert_refused(fn() => rule_manager::process_form($this->rule_formdata($this->freecohorts['system'])));

        // Ids that do not exist are refused before the validation could report them.
        $this->assert_refused(fn() => rule_manager::process_form($this->rule_formdata(999999)));
        $this->assert_refused(fn() => rule_manager::process_form($this->rule_formdata($this->freecohorts['a'], 999999)));

        // The control: a rule for a cohort in A1.
        $rule = rule_manager::process_form($this->rule_formdata($this->freecohorts['a1']));
        $this->assertEquals($this->contexts['a1']->id, $rule->get_context()->id);
    }

    /**
     * Moving a rule between cohorts needs both of them.
     */
    public function test_moving_a_rule_needs_both_cohorts(): void {
        $this->setUser($this->manager);

        // From A to B: the new cohort is out of reach.
        $ruleid = (int) $this->rules['a']->get('id');
        $this->assert_refused(fn() => rule_manager::process_form($this->rule_formdata($this->freecohorts['b'], $ruleid)));

        // From B to A: the current cohort is out of reach.
        $ruleid = (int) $this->rules['b']->get('id');
        $this->assert_refused(fn() => rule_manager::process_form($this->rule_formdata($this->freecohorts['a'], $ruleid)));
        $this->assertEquals($this->cohorts['b'], (int) rule::get_record(['id' => $ruleid])->get('cohortid'));

        // The control: within A.
        $ruleid = (int) $this->rules['a']->get('id');
        rule_manager::process_form($this->rule_formdata($this->freecohorts['a1'], $ruleid));
        $this->assertEquals($this->freecohorts['a1'], (int) rule::get_record(['id' => $ruleid])->get('cohortid'));
    }

    /**
     * The cohort list of the rule form: cohorts at or below the page the user may attach a rule to.
     */
    public function test_cohort_list_is_scoped(): void {
        $this->setUser($this->manager);

        $cohorts = array_keys(cohort_manager::get_cohorts(true, $this->contexts['a']));
        sort($cohorts);
        $expected = [$this->cohorts['a'], $this->freecohorts['a'], $this->cohorts['a1'], $this->freecohorts['a1']];
        sort($expected);
        $this->assertEquals($expected, $cohorts);

        // Without a context the list is unchecked, as rule_manager's own validation reads it.
        $this->assertCount(10, cohort_manager::get_cohorts(true));

        // Someone who may manage everywhere still sees only the page's subtree on A's page.
        $this->setAdminUser();
        $cohorts = array_keys(cohort_manager::get_cohorts(true, $this->contexts['a']));
        sort($cohorts);
        $expected = array_merge($expected, [$this->cohorts['a2'], $this->freecohorts['a2']]);
        sort($expected);
        $this->assertEquals($expected, $cohorts);
    }

    /**
     * The rule form opens on an existing rule's cohort context, else on the page's.
     */
    public function test_rule_form_access(): void {
        $this->setUser($this->manager);
        $form = fn(array $data) => new rule_form(null, null, 'post', '', [], true, $data, true);

        $this->assert_refused(fn() => $form(['id' => (int) $this->rules['b']->get('id')]));
        $this->assert_refused(fn() => $form(['id' => 0, 'contextid' => $this->contexts['b']->id]));
        $this->assert_refused(fn() => $form(['id' => 0]));

        // An existing rule is decided on its own cohort, whatever page the client claims.
        $this->assert_refused(fn() => $form(['id' => (int) $this->rules['b']->get('id'), 'contextid' => $this->contexts['a']->id]));
        // A rule id that does not exist is refused, not reported as missing.
        $this->assert_refused(fn() => $form(['id' => 999999, 'contextid' => $this->contexts['a']->id]));

        // The controls: an existing rule in A without a contextid, and a new rule from A's page.
        $this->assertInstanceOf(rule_form::class, $form(['id' => (int) $this->rules['a1']->get('id')]));
        $this->assertInstanceOf(rule_form::class, $form(['id' => 0, 'contextid' => $this->contexts['a']->id]));
    }

    /**
     * The condition modal's web service is decided in the page's context, which must be a cohort's level.
     */
    public function test_condition_form_service_context(): void {
        $this->setUser($this->manager);
        $classname = 'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_last_login';
        $formdata = json_encode(http_build_query(['id' => 0, 'sortorder' => 0, 'operator' => 1,
            '_qf__tool_dynamic_cohorts_condition_form' => 1, 'sesskey' => sesskey()]));
        $_POST['sesskey'] = sesskey();

        $this->assert_refused(fn() => condition_form::submit($classname, $formdata, $this->contexts['b']->id));
        $this->assert_refused(fn() => condition_form::submit($classname, $formdata, 0));

        $course = $this->getDataGenerator()->create_course(['category' => $this->contexts['a']->instanceid]);
        try {
            condition_form::submit($classname, $formdata, \core\context\course::instance($course->id)->id);
            $this->fail('A course context was accepted.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidcontext', $exception->errorcode);
        }

        // The control: from A's page.
        $result = condition_form::submit($classname, $formdata, $this->contexts['a']->id);
        $this->assertSame($classname, $result['classname']);
    }

    /**
     * Both fragments are decided before they render anything.
     */
    public function test_fragments(): void {
        global $PAGE;

        $this->setUser($this->manager);
        // The rules page sets it in a real request; the report table reads it when it renders.
        $PAGE->set_url('/admin/tool/dynamic_cohorts/index.php');

        $this->assert_refused(fn() => tool_dynamic_cohorts_output_fragment_matching_users(
            ['ruleid' => (int) $this->rules['b']->get('id')]
        ));
        $this->assert_refused(fn() => tool_dynamic_cohorts_output_fragment_condition_form([
            'context' => $this->contexts['b'],
            'classname' => 'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_last_login',
        ]));

        // Only a system or course category page, even where the user holds the capabilities.
        $course = $this->getDataGenerator()->create_course(['category' => $this->contexts['a']->instanceid]);
        try {
            tool_dynamic_cohorts_output_fragment_condition_form([
                'context' => \core\context\course::instance($course->id),
                'classname' => 'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_last_login',
            ]);
            $this->fail('A course context was accepted.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidcontext', $exception->errorcode);
        }

        // The controls.
        $this->assertIsString(tool_dynamic_cohorts_output_fragment_matching_users(
            ['ruleid' => (int) $this->rules['a']->get('id')]
        ));
        $this->assertIsString(tool_dynamic_cohorts_output_fragment_condition_form([
            'context' => $this->contexts['a'],
            'classname' => 'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_last_login',
        ]));
    }

    /**
     * The matching-users report is decided on its rule, not on the context it is created in.
     */
    public function test_matching_users_report_reads_the_rule(): void {
        $this->setUser($this->manager);

        $create = fn(context $context, rule $rule) => system_report_factory::create(
            matching_users::class,
            $context,
            'tool_dynamic_cohorts',
            '',
            0,
            ['ruleid' => (int) $rule->get('id')]
        );

        try {
            $create($this->contexts['a'], $this->rules['b']);
            $this->fail('A rule in B was listed through a report created in A.');
        } catch (\core_reportbuilder\exception\report_access_exception $exception) {
            $this->assertInstanceOf(\core_reportbuilder\exception\report_access_exception::class, $exception);
        }

        // A rule that does not exist is refused the same way, not reported as missing.
        $missing = new rule(0, (object) ['name' => 'Missing']);
        $missing->set('id', 999999);
        try {
            $create($this->contexts['a'], $missing);
            $this->fail('A missing rule was not refused.');
        } catch (\core_reportbuilder\exception\report_access_exception $exception) {
            $this->assertInstanceOf(\core_reportbuilder\exception\report_access_exception::class, $exception);
        }

        // The control.
        $this->assertInstanceOf(matching_users::class, $create($this->contexts['a'], $this->rules['a1']));
    }

    /**
     * Rule and condition events land in the rule's cohort context, also after the rule is gone.
     */
    public function test_events_carry_the_rule_context(): void {
        $this->setUser($this->manager);
        $sink = $this->redirectEvents();

        $rule = rule_manager::process_form($this->rule_formdata($this->freecohorts['a1']));
        $created = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof rule_created));
        $this->assertEquals($this->contexts['a1']->id, $created[0]->contextid);

        $condition = new condition(0, (object) ['ruleid' => $rule->get('id'), 'classname' => 'x', 'sortorder' => 0,
            'configdata' => '{}']);
        $condition->save();
        $sink->clear();

        rule_manager::delete_rule($rule);
        $deleted = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof condition_deleted));
        $this->assertEquals($this->contexts['a1']->id, $deleted[0]->contextid);
    }
}

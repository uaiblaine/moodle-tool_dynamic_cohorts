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
use PHPUnit\Framework\Attributes\DataProvider;
use tool_dynamic_cohorts\condition;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_form;
use tool_dynamic_cohorts\condition_manager;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\rule_form;
use tool_dynamic_cohorts\rule_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Conditions written by a user who manages rules in a course category only (stage S3).
 *
 * The manager holds tool/dynamic_cohorts:manage and moodle/cohort:assign in category A only; A1
 * is a subcategory of A, B a sibling category. Every check is made where it counts: when the
 * rule is saved (rule_manager::process_form()), against the cohort the rule fills.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(condition_manager::class)]
#[CoversClass(condition_base::class)]
#[CoversClass(rule_access::class)]
#[CoversClass(condition_form::class)]
#[CoversClass(rule_form::class)]
final class condition_scope_test extends \advanced_testcase {
    /** @var string Namespace of the built-in conditions. */
    private const NS = 'tool_dynamic_cohorts\\local\\tool_dynamic_cohorts\\condition\\';

    /** @var array Contexts by key: a, a1, b, system. */
    private array $contexts = [];

    /** @var array Courses by key: a, a1, b; completion enabled. */
    private array $courses = [];

    /** @var array Cohorts by key: a1, b, systemvisible, systemhidden. */
    private array $cohorts = [];

    /** @var rule A rule filling a cohort of category A. */
    private rule $rule;

    /** @var int The category manager. */
    private int $manager;

    /** @var int The role the manager holds in A. */
    private int $roleid;

    /**
     * Build categories, courses, cohorts, the rule and the manager.
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        // A refused save rolls its own transaction back; inside the test's it could not.
        $this->preventResetByRollback();
        $CFG->enablecompletion = 1;
        $generator = $this->getDataGenerator();

        $a = $generator->create_category();
        $a1 = $generator->create_category(['parent' => $a->id]);
        $b = $generator->create_category();
        $this->contexts = [
            'a' => \core\context\coursecat::instance($a->id),
            'a1' => \core\context\coursecat::instance($a1->id),
            'b' => \core\context\coursecat::instance($b->id),
            'system' => \core\context\system::instance(),
        ];

        $this->courses = [
            'a' => $generator->create_course(['category' => $a->id, 'enablecompletion' => 1]),
            'a1' => $generator->create_course(['category' => $a1->id, 'enablecompletion' => 1]),
            'b' => $generator->create_course(['category' => $b->id, 'enablecompletion' => 1]),
        ];

        $this->cohorts = [
            'a1' => $generator->create_cohort(['contextid' => $this->contexts['a1']->id, 'name' => 'Match A1']),
            'b' => $generator->create_cohort(['contextid' => $this->contexts['b']->id, 'name' => 'Match B']),
            'systemvisible' => $generator->create_cohort(['name' => 'Match system']),
            'systemhidden' => $generator->create_cohort(['name' => 'Hidden system', 'visible' => 0]),
        ];

        $rulecohort = $generator->create_cohort(['contextid' => $this->contexts['a']->id, 'name' => 'Rule cohort']);
        $this->rule = new rule(0, (object) ['name' => 'Rule', 'cohortid' => $rulecohort->id]);
        $this->rule->save();

        $this->roleid = $generator->create_role();
        assign_capability('tool/dynamic_cohorts:manage', CAP_ALLOW, $this->roleid, $this->contexts['a']->id);
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $this->roleid, $this->contexts['a']->id);
        $this->manager = (int) $generator->create_user()->id;
        role_assign($this->roleid, $this->manager, $this->contexts['a']->id);
    }

    /**
     * Save the rule with one new condition, as rule_form would.
     *
     * @param string $condition Condition class name without the namespace.
     * @param array $configdata Its config data.
     * @return rule
     */
    private function save_with(string $condition, array $configdata): rule {
        $formdata = (object) ['id' => $this->rule->get('id'), 'name' => 'Rule', 'cohortid' => $this->rule->get('cohortid'),
            'description' => '', 'bulkprocessing' => 0, 'realtime' => 0,
            'operator' => rule_manager::CONDITIONS_OPERATOR_AND, 'isconditionschanged' => true,
            'conditionjson' => json_encode([
                ['id' => 0, 'classname' => self::NS . $condition, 'sortorder' => 0, 'configdata' => json_encode($configdata)],
            ])];
        return rule_manager::process_form($formdata);
    }

    /**
     * Assert that saving a condition is refused because it reaches outside the category.
     *
     * @param string $condition Condition class name without the namespace.
     * @param array $configdata Its config data.
     */
    private function assert_out_of_scope(string $condition, array $configdata): void {
        try {
            $this->save_with($condition, $configdata);
            $this->fail('A condition reaching outside the category was saved.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('conditionoutofscope', $exception->errorcode);
        }
    }

    /**
     * Data provider: the course conditions and their config.
     *
     * @return array
     */
    public static function course_condition_provider(): array {
        return [
            'course completed' => ['course_completed', ['operator' => 1]],
            'course not completed' => ['course_not_completed', []],
            'user enrolment' => ['user_enrolment', ['operator' => 1, 'enrolmethod' => '', 'roleid' => 0]],
        ];
    }

    /**
     * A course condition takes only courses of the category.
     *
     * @param string $condition Condition class name without the namespace.
     * @param array $config Config other than the course.
     */
    #[DataProvider('course_condition_provider')]
    public function test_course_conditions(string $condition, array $config): void {
        $this->setUser($this->manager);
        $this->assert_out_of_scope($condition, $config + ['courseid' => $this->courses['b']->id]);

        // The controls: a course of A1; and an administrator is not held to the category.
        $this->save_with($condition, $config + ['courseid' => $this->courses['a1']->id]);
        $this->setAdminUser();
        $this->save_with($condition, $config + ['courseid' => $this->courses['b']->id]);
        $this->assertCount(1, $this->rule->get_condition_records());
    }

    /**
     * A role condition takes only a category or a course of the category, never the system.
     */
    public function test_user_role(): void {
        $this->setUser($this->manager);
        $config = ['operator' => 1, 'roleid' => $this->roleid];

        $this->assert_out_of_scope('user_role', $config + ['contextlevel' => CONTEXT_SYSTEM]);
        $this->assert_out_of_scope('user_role', $config + ['contextlevel' => CONTEXT_COURSECAT,
            'categoryid' => $this->contexts['b']->instanceid]);
        $this->assert_out_of_scope('user_role', $config + ['contextlevel' => CONTEXT_COURSE,
            'courseid' => $this->courses['b']->id]);

        // The controls.
        $this->save_with('user_role', $config + ['contextlevel' => CONTEXT_COURSECAT,
            'categoryid' => $this->contexts['a1']->instanceid]);
        $this->save_with('user_role', $config + ['contextlevel' => CONTEXT_COURSE, 'courseid' => $this->courses['a1']->id]);
        $this->assertCount(1, $this->rule->get_condition_records());
    }

    /**
     * Cohort membership takes the cohorts usable from the category: its own and the visible ones above.
     */
    public function test_cohort_membership(): void {
        $this->setUser($this->manager);
        $config = fn(string $key) => ['cohort_membership_operator' => 1,
            'cohort_membership_value' => [(int) $this->cohorts[$key]->id]];

        $this->assert_out_of_scope('cohort_membership', $config('b'));
        $this->assert_out_of_scope('cohort_membership', $config('systemhidden'));

        // The controls.
        $this->save_with('cohort_membership', $config('a1'));
        $this->save_with('cohort_membership', $config('systemvisible'));
        $this->assertCount(1, $this->rule->get_condition_records());
    }

    /**
     * Config of a cohort_field condition matching cohorts whose name contains "Match".
     *
     * @return array
     */
    private function cohort_name_config(): array {
        return ['cohort_field_field' => 'name', 'cohort_field_operator' => 1,
            'name_operator' => condition_base::TEXT_CONTAINS, 'name_value' => 'Match'];
    }

    /**
     * Users matched by the rule's only condition, by id.
     *
     * @return array
     */
    private function matched(): array {
        $ids = array_map('intval', array_keys(rule_manager::get_matching_users($this->rule)));
        sort($ids);
        return $ids;
    }

    /**
     * Cohort fields are scoped while the rule is processed, from the scope recorded on save.
     */
    public function test_cohort_field_records_its_scope(): void {
        $generator = $this->getDataGenerator();
        $members = [];
        foreach (['a1', 'b', 'systemvisible'] as $key) {
            $members[$key] = (int) $generator->create_user()->id;
            cohort_add_member($this->cohorts[$key]->id, $members[$key]);
        }

        // A client-sent scope is overwritten with the rule's category.
        $this->setUser($this->manager);
        $this->rule = $this->save_with('cohort_field', $this->cohort_name_config() + ['scopecontextid' => SYSCONTEXTID]);
        $condition = array_values($this->rule->get_condition_records())[0];
        $this->assertEquals($this->contexts['a']->id, json_decode($condition->get('configdata'), true)['scopecontextid']);

        // Processing runs without a user: the recorded scope keeps B's members out.
        $this->setUser(null);
        $expected = [$members['a1'], $members['systemvisible']];
        sort($expected);
        $this->assertSame($expected, $this->matched());

        // The control: an administrator's condition records no scope and matches every cohort.
        $this->setAdminUser();
        $this->rule = $this->save_with('cohort_field', $this->cohort_name_config());
        $condition = array_values($this->rule->get_condition_records())[0];
        $this->assertArrayNotHasKey('scopecontextid', json_decode($condition->get('configdata'), true));
        $expected[] = $members['b'];
        sort($expected);
        $this->assertSame($expected, $this->matched());
    }

    /**
     * A condition an administrator wrote, out of the category, blocks a category manager's save.
     */
    public function test_stored_condition_out_of_scope_blocks_the_save(): void {
        $this->setAdminUser();
        $this->rule = $this->save_with('course_completed', ['operator' => 1, 'courseid' => $this->courses['b']->id]);
        $stored = array_values($this->rule->get_condition_records())[0];

        $this->setUser($this->manager);
        $base = ['id' => $this->rule->get('id'), 'name' => 'Renamed', 'cohortid' => $this->rule->get('cohortid'),
            'description' => '', 'bulkprocessing' => 0, 'realtime' => 0, 'operator' => rule_manager::CONDITIONS_OPERATOR_AND];

        // Conditions untouched, only the rule's fields.
        try {
            rule_manager::process_form((object) ($base + ['conditionjson' => '']));
            $this->fail('A stored out-of-scope condition was kept by a category manager.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('conditionoutofscope', $exception->errorcode);
        }

        // Conditions sent back unchanged.
        $unchanged = json_encode([['id' => (int) $stored->get('id'), 'classname' => $stored->get('classname'),
            'sortorder' => 0, 'configdata' => $stored->get('configdata')]]);
        try {
            rule_manager::process_form((object) ($base + ['conditionjson' => $unchanged, 'isconditionschanged' => true]));
            $this->fail('A stored out-of-scope condition sent back unchanged was saved.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('conditionoutofscope', $exception->errorcode);
        }

        $this->assertSame('Rule', rule::get_record(['id' => $this->rule->get('id')])->get('name'));
    }

    /**
     * The setting decides which conditions a category manager may use.
     */
    public function test_category_conditions_setting(): void {
        // Unset: every built-in condition.
        $this->assertCount(12, condition_manager::category_conditions());

        set_config('categoryconditions', self::NS . 'course_completed', 'tool_dynamic_cohorts');
        $this->assertSame([self::NS . 'course_completed'], condition_manager::category_conditions());

        $this->setUser($this->manager);
        try {
            $this->save_with('user_last_login', ['operator' => 1]);
            $this->fail('A condition switched off for categories was saved.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('conditionnotincategory', $exception->errorcode);
        }

        // The control: an administrator may still use it.
        $this->setAdminUser();
        $this->save_with('user_last_login', ['operator' => 1]);
        $this->assertCount(1, $this->rule->get_condition_records());
    }

    /**
     * Identity fields are offered only where the manager may see them.
     */
    public function test_user_profile_identity_fields(): void {
        global $CFG;

        $config = ['profilefield' => 'email', 'email_operator' => condition_base::TEXT_CONTAINS, 'email_value' => 'x'];
        $this->setUser($this->manager);
        $this->assert_out_of_scope('user_profile', $config);

        // The controls: a non-identity field; and email where core would show it to the manager.
        $city = ['profilefield' => 'city', 'city_operator' => condition_base::TEXT_CONTAINS, 'city_value' => 'x'];
        $this->save_with('user_profile', $city);
        // Unless the site hides it: core shows a hidden field only with viewhiddendetails over the user.
        $CFG->hiddenuserfields = 'city';
        $this->assert_out_of_scope('user_profile', $city);
        $CFG->hiddenuserfields = '';
        $CFG->showuseridentity = 'email';
        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $this->roleid, $this->contexts['a']->id);
        $this->save_with('user_profile', $config);
        $this->assertCount(1, $this->rule->get_condition_records());
    }

    /**
     * Custom profile fields follow their visibility.
     */
    public function test_user_custom_profile_visibility(): void {
        global $DB;

        $fields = [];
        $visibilities = ['everyone' => PROFILE_VISIBLE_ALL, 'teachers' => PROFILE_VISIBLE_TEACHERS,
            'private' => PROFILE_VISIBLE_PRIVATE];
        foreach ($visibilities as $shortname => $visible) {
            $fields[$shortname] = $DB->insert_record('user_info_field', (object) ['shortname' => $shortname,
                'name' => $shortname, 'datatype' => 'text', 'description' => '', 'categoryid' => 0,
                'visible' => $visible, 'required' => 0, 'locked' => 0, 'forceunique' => 0, 'signup' => 0]);
        }
        $config = fn(string $shortname) => ['profilefield' => 'profile_field_' . $shortname,
            'profile_field_' . $shortname . '_operator' => condition_base::TEXT_CONTAINS,
            'profile_field_' . $shortname . '_value' => 'x'];

        $this->setUser($this->manager);
        $this->assert_out_of_scope('user_custom_profile', $config('teachers'));
        $this->assert_out_of_scope('user_custom_profile', $config('private'));

        // The controls: a field everyone sees; a teachers' field with viewuseridentity in A.
        $this->save_with('user_custom_profile', $config('everyone'));
        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $this->roleid, $this->contexts['a']->id);
        $this->save_with('user_custom_profile', $config('teachers'));
        $this->assertCount(1, $this->rule->get_condition_records());
    }

    /**
     * The condition modal refuses a condition switched off for categories, and checks the scope.
     */
    public function test_condition_modal(): void {
        $this->setUser($this->manager);
        $_POST['sesskey'] = sesskey();
        $data = fn(array $config) => json_encode(http_build_query($config + ['id' => 0, 'sortorder' => 0,
            '_qf__tool_dynamic_cohorts_condition_form' => 1, 'sesskey' => sesskey()], '', '&'));

        set_config('categoryconditions', self::NS . 'course_completed', 'tool_dynamic_cohorts');
        try {
            $lastlogin = $data(['operator' => 1]);
            $contextid = $this->contexts['a']->id;
            \tool_dynamic_cohorts\external\condition_form::submit(self::NS . 'user_last_login', $lastlogin, $contextid);
            $this->fail('A condition switched off for categories was offered.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('conditionnotincategory', $exception->errorcode);
        }

        // The form itself refuses a course of B: the course list is A's subtree.
        $customdata = ['classname' => self::NS . 'course_completed', 'scope' => $this->contexts['a']];
        $submitted = fn(string $key) => ['courseid' => $this->courses[$key]->id, 'operator' => 1, 'id' => 0, 'sortorder' => 0,
            '_qf__tool_dynamic_cohorts_condition_form' => 1, 'sesskey' => sesskey()];
        $form = new condition_form(null, $customdata, 'post', '', null, true, $submitted('b'));
        $this->assertFalse($form->is_validated());

        // The control: a course of A1.
        $form = new condition_form(null, $customdata, 'post', '', null, true, $submitted('a1'));
        $this->assertTrue($form->is_validated());
    }

    /**
     * The rule form offers a category manager only the conditions available in a category.
     */
    public function test_rule_form_offers_category_conditions(): void {
        $this->setUser($this->manager);
        set_config('categoryconditions', self::NS . 'course_completed', 'tool_dynamic_cohorts');

        $html = (new rule_form(null, null, 'post', '', [], true, ['id' => 0, 'contextid' => $this->contexts['a']->id], true))
            ->render();
        $this->assertMatchesRegularExpression('/<option value="' . preg_quote(self::NS . 'course_completed', '/') . '"/', $html);
        $lastlogin = preg_quote(self::NS . 'user_last_login', '/');
        $this->assertDoesNotMatchRegularExpression('/<option value="' . $lastlogin . '"/', $html);
    }

    /**
     * A rule moved to another cohort keeps its conditions only if they fit the new cohort's category.
     */
    public function test_moved_rule_keeps_only_conditions_in_scope(): void {
        $this->setUser($this->manager);
        $this->rule = $this->save_with('course_completed', ['operator' => 1, 'courseid' => $this->courses['a']->id]);

        $cohorta1 = $this->getDataGenerator()->create_cohort(['contextid' => $this->contexts['a1']->id]);
        $move = fn(int $cohortid) => rule_manager::process_form((object) ['id' => $this->rule->get('id'), 'name' => 'Rule',
            'cohortid' => $cohortid, 'description' => '', 'bulkprocessing' => 0, 'realtime' => 0,
            'operator' => rule_manager::CONDITIONS_OPERATOR_AND, 'conditionjson' => '']);

        // A course of A does not lie in A1.
        try {
            $move((int) $cohorta1->id);
            $this->fail('A rule was moved to a cohort whose category its condition reaches outside.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('conditionoutofscope', $exception->errorcode);
        }
        $this->assertEquals($this->rule->get('cohortid'), rule::get_record(['id' => $this->rule->get('id')])->get('cohortid'));
    }

    /**
     * Scoped config data that is not a JSON object is refused as invalid, not with a TypeError.
     */
    public function test_scoped_invalid_config_is_refused(): void {
        $this->setUser($this->manager);
        $formdata = (object) ['id' => $this->rule->get('id'), 'name' => 'Rule', 'cohortid' => $this->rule->get('cohortid'),
            'description' => '', 'bulkprocessing' => 0, 'realtime' => 0,
            'operator' => rule_manager::CONDITIONS_OPERATOR_AND, 'isconditionschanged' => true,
            'conditionjson' => json_encode([['id' => 0, 'classname' => self::NS . 'cohort_field', 'sortorder' => 0,
                'configdata' => '"x"']])];

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Invalid config data');
        rule_manager::process_form($formdata);
    }

    /**
     * The condition-form fragment refuses a condition switched off for categories.
     */
    public function test_condition_fragment(): void {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/dynamic_cohorts/lib.php');

        $this->setUser($this->manager);
        set_config('categoryconditions', self::NS . 'course_completed', 'tool_dynamic_cohorts');

        $args = fn(string $condition) => ['context' => $this->contexts['a'], 'classname' => self::NS . $condition];
        try {
            tool_dynamic_cohorts_output_fragment_condition_form($args('user_last_login'));
            $this->fail('A condition switched off for categories was rendered.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('conditionnotincategory', $exception->errorcode);
        }

        // The control.
        $this->assertIsString(tool_dynamic_cohorts_output_fragment_condition_form($args('course_completed')));
    }
}

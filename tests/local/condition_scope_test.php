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
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\competency_proficiency;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\learning_plan;

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
        $this->assertCount(14, condition_manager::category_conditions());

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
     * Move the rule to another cohort, keeping its conditions, as rule_form would.
     *
     * @param int $cohortid The cohort to fill from now on.
     */
    private function move_rule(int $cohortid): void {
        rule_manager::process_form((object) ['id' => $this->rule->get('id'), 'name' => 'Rule', 'cohortid' => $cohortid,
            'description' => '', 'bulkprocessing' => 0, 'realtime' => 0,
            'operator' => rule_manager::CONDITIONS_OPERATOR_AND, 'conditionjson' => '']);
        $this->rule = rule::get_record(['id' => $this->rule->get('id')]);
    }

    /**
     * The category a condition records, read from the stored row.
     *
     * @param int $index Position of the condition in the rule.
     * @return int|null Context id, or null when none is recorded.
     */
    private function recorded_scope(int $index = 0): ?int {
        $condition = array_values($this->rule->get_condition_records())[$index];
        $config = json_decode($condition->get('configdata'), true);
        return isset($config[condition_base::SCOPE_KEY]) ? (int) $config[condition_base::SCOPE_KEY] : null;
    }

    /**
     * A rule moved to a narrower category holds its kept conditions to that category.
     */
    public function test_moved_rule_records_the_new_scope(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $this->setUser($this->manager);
        $this->rule = $this->save_with('course_completed', ['operator' => 1, 'courseid' => $this->courses['a1']->id]);
        $this->assertSame((int) $this->contexts['a']->id, $this->recorded_scope());

        $cohorta1 = $this->getDataGenerator()->create_cohort(['contextid' => $this->contexts['a1']->id]);
        $this->move_rule((int) $cohorta1->id);
        $this->assertSame((int) $this->contexts['a1']->id, $this->recorded_scope());
        $this->assertFalse($this->rule->is_broken(true));

        // Still in A, no longer in A1, where the rule now fills its cohort.
        $this->setAdminUser();
        move_courses([$this->courses['a1']->id], $this->contexts['a']->instanceid);
        $this->assertTrue($this->rule->is_broken(true));
    }

    /**
     * A condition an administrator wrote, sent back unchanged by a category manager, records the category.
     */
    public function test_unchanged_condition_records_the_scope(): void {
        $this->setAdminUser();
        $this->rule = $this->save_with('course_completed', ['operator' => 1, 'courseid' => $this->courses['a1']->id]);
        $this->assertNull($this->recorded_scope());

        $this->setUser($this->manager);
        $stored = array_values($this->rule->get_condition_records())[0];
        rule_manager::process_form((object) ['id' => $this->rule->get('id'), 'name' => 'Rule',
            'cohortid' => $this->rule->get('cohortid'), 'description' => '', 'bulkprocessing' => 0, 'realtime' => 0,
            'operator' => rule_manager::CONDITIONS_OPERATOR_AND, 'isconditionschanged' => true,
            'conditionjson' => json_encode([['id' => (int) $stored->get('id'), 'classname' => $stored->get('classname'),
                'sortorder' => 0, 'configdata' => $stored->get('configdata')]])]);
        $this->rule = rule::get_record(['id' => $this->rule->get('id')]);

        $this->assertSame((int) $this->contexts['a']->id, $this->recorded_scope());
    }

    /**
     * A move refused over one condition records nothing on the conditions checked before it.
     */
    public function test_refused_move_records_nothing(): void {
        $this->setAdminUser();
        $condition = fn(int $courseid, int $sortorder) => ['id' => 0, 'classname' => self::NS . 'course_completed',
            'sortorder' => $sortorder, 'configdata' => json_encode(['operator' => 1, 'courseid' => $courseid])];
        $this->rule = rule_manager::process_form((object) ['id' => $this->rule->get('id'), 'name' => 'Rule',
            'cohortid' => $this->rule->get('cohortid'), 'description' => '', 'bulkprocessing' => 0, 'realtime' => 0,
            'operator' => rule_manager::CONDITIONS_OPERATOR_AND, 'isconditionschanged' => true,
            'conditionjson' => json_encode([$condition((int) $this->courses['a1']->id, 0),
                $condition((int) $this->courses['a']->id, 1)])]);

        $this->setUser($this->manager);
        $cohorta1 = $this->getDataGenerator()->create_cohort(['contextid' => $this->contexts['a1']->id]);
        try {
            $this->move_rule((int) $cohorta1->id);
            $this->fail('A rule was moved to a cohort whose category its second condition reaches outside.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('conditionoutofscope', $exception->errorcode);
        }

        $this->rule = rule::get_record(['id' => $this->rule->get('id')]);
        $this->assertSame('course_completed', substr(array_values($this->rule->get_condition_records())[0]->get('classname'), -16));
        $this->assertNull($this->recorded_scope(0));
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

    /**
     * A category manager's conditions all record the category they were written in.
     */
    public function test_scope_recorded_for_every_condition(): void {
        $this->setUser($this->manager);
        $this->rule = $this->save_with('user_last_login', ['operator' => 1]);

        $condition = array_values($this->rule->get_condition_records())[0];
        $config = json_decode($condition->get('configdata'), true);
        $this->assertEquals($this->contexts['a']->id, $config[condition_base::SCOPE_KEY]);
    }

    /**
     * Data provider: the course conditions that are checked again while processing.
     *
     * @return array
     */
    public static function moved_course_provider(): array {
        return [
            'course completed' => ['course_completed', ['operator' => 1]],
            'user enrolment' => ['user_enrolment', ['operator' => 1, 'enrolmethod' => '', 'roleid' => 0]],
        ];
    }

    /**
     * A course moved out of the category breaks the rule while it is processed; members stay.
     *
     * @param string $condition Condition class name without the namespace.
     * @param array $config Config other than the course.
     */
    #[DataProvider('moved_course_provider')]
    public function test_moved_course_breaks_the_rule(string $condition, array $config): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $this->setUser($this->manager);
        $this->rule = $this->save_with($condition, $config + ['courseid' => $this->courses['a1']->id]);
        $this->rule->set('enabled', 1);
        $this->rule->save();
        $member = (int) $this->getDataGenerator()->create_user()->id;
        cohort_add_member($this->rule->get('cohortid'), $member);

        // The control: in place, nothing is broken.
        $this->assertFalse($this->rule->is_broken(true));

        $this->setAdminUser();
        move_courses([$this->courses['a1']->id], $this->contexts['b']->instanceid);

        $this->setUser(null);
        rule_manager::process_rule($this->rule);
        $this->assertTrue((bool) rule::get_record(['id' => $this->rule->get('id')])->get('broken'));
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $this->rule->get('cohortid'), 'userid' => $member]));

        $instance = condition_base::get_instance(0, array_values($this->rule->get_condition_records())[0]->to_record());
        $this->assertSame(get_string('brokenoutofscope', 'tool_dynamic_cohorts'), $instance->get_broken_description());
    }

    /**
     * A category moved out of the category breaks a role condition naming it.
     */
    public function test_moved_category_breaks_user_role(): void {
        $this->setUser($this->manager);
        $this->rule = $this->save_with('user_role', ['operator' => 1, 'roleid' => $this->roleid,
            'contextlevel' => CONTEXT_COURSECAT, 'categoryid' => $this->contexts['a1']->instanceid]);
        $this->assertFalse($this->rule->is_broken(true));

        $this->setAdminUser();
        \core_course_category::get($this->contexts['a1']->instanceid)->change_parent($this->contexts['b']->instanceid);

        $this->assertTrue($this->rule->is_broken(true));
    }

    /**
     * A parent's cohort hidden after the save breaks a membership condition naming it.
     */
    public function test_hidden_cohort_breaks_cohort_membership(): void {
        global $DB;

        $this->setUser($this->manager);
        $this->rule = $this->save_with('cohort_membership', ['cohort_membership_operator' => 1,
            'cohort_membership_value' => [(int) $this->cohorts['systemvisible']->id]]);
        $this->assertFalse($this->rule->is_broken(true));

        $DB->set_field('cohort', 'visible', 0, ['id' => $this->cohorts['systemvisible']->id]);

        $this->assertTrue($this->rule->is_broken(true));
    }

    /**
     * A condition an administrator wrote records no category and is not checked again.
     */
    public function test_site_wide_condition_is_not_rechecked(): void {
        $this->setAdminUser();
        $this->rule = $this->save_with('course_completed', ['operator' => 1, 'courseid' => $this->courses['b']->id]);

        $condition = array_values($this->rule->get_condition_records())[0];
        $this->assertArrayNotHasKey(condition_base::SCOPE_KEY, json_decode($condition->get('configdata'), true));
        $this->assertFalse($this->rule->is_broken(true));
    }

    /**
     * A condition whose recorded category is gone is broken.
     */
    public function test_recorded_category_gone_breaks(): void {
        $build = fn(int $scopeid) => condition_base::get_instance(0, (object) [
            'classname' => self::NS . 'course_completed',
            'configdata' => json_encode(['operator' => 1, 'courseid' => (int) $this->courses['a1']->id,
                condition_base::SCOPE_KEY => $scopeid]),
        ]);

        // The control: the category A it was written in exists.
        $this->assertFalse($build((int) $this->contexts['a']->id)->is_broken());
        $this->assertTrue($build(999999)->is_broken());
    }

    /**
     * Frameworks of A (hidden), B, the system (visible and hidden), each with one competency.
     *
     * @return int[] Competency ids by key: a, b, systemvisible, systemhidden.
     */
    private function create_competencies(): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $frameworks = [
            'a' => ['contextid' => $this->contexts['a']->id, 'visible' => 0],
            'b' => ['contextid' => $this->contexts['b']->id, 'visible' => 1],
            'systemvisible' => ['contextid' => $this->contexts['system']->id, 'visible' => 1],
            'systemhidden' => ['contextid' => $this->contexts['system']->id, 'visible' => 0],
        ];
        $ids = [];
        foreach ($frameworks as $key => $record) {
            $framework = $generator->create_framework($record);
            $ids[$key] = (int) $generator->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');
        }
        return $ids;
    }

    /**
     * Config of a competency proficiency condition on one competency and source.
     *
     * @param int $competencyid Competency id.
     * @param int $source One of the SOURCE_ constants.
     * @param int $courseid Course of the one-course source.
     * @return array
     */
    private function competency_config(int $competencyid, int $source, int $courseid = 0): array {
        return ['competencyids' => [$competencyid], 'match' => competency_proficiency::MATCH_ANY,
            'state' => competency_proficiency::STATE_PROFICIENT, 'source' => $source, 'courseid' => $courseid];
    }

    /**
     * Competencies come from the frameworks usable from the category; the site-wide rating only from its own.
     */
    public function test_competency_proficiency(): void {
        $competencies = $this->create_competencies();
        $global = competency_proficiency::SOURCE_GLOBAL;
        $anycourse = competency_proficiency::SOURCE_ANY_COURSE;
        $course = competency_proficiency::SOURCE_COURSE;
        $this->setUser($this->manager);

        $this->assert_out_of_scope('competency_proficiency', $this->competency_config($competencies['b'], $anycourse));
        $this->assert_out_of_scope('competency_proficiency', $this->competency_config($competencies['systemhidden'], $anycourse));
        // One usable competency does not carry an unusable one with it.
        $mixed = ['competencyids' => [$competencies['a'], $competencies['b']]] + $this->competency_config(0, $anycourse);
        $this->assert_out_of_scope('competency_proficiency', $mixed);
        // Q1: the site-wide rating of a framework from above is earned anywhere on the site.
        $this->assert_out_of_scope('competency_proficiency', $this->competency_config($competencies['systemvisible'], $global));
        $incourseb = $this->competency_config($competencies['systemvisible'], $course, (int) $this->courses['b']->id);
        $this->assert_out_of_scope('competency_proficiency', $incourseb);

        // The controls: the category's own framework site-wide, one from above in its courses; the administrator free.
        $this->save_with('competency_proficiency', $this->competency_config($competencies['a'], $global));
        $this->save_with('competency_proficiency', $this->competency_config($competencies['systemvisible'], $anycourse));
        $incoursea1 = $this->competency_config($competencies['systemvisible'], $course, (int) $this->courses['a1']->id);
        $this->save_with('competency_proficiency', $incoursea1);
        $this->setAdminUser();
        $this->save_with('competency_proficiency', $this->competency_config($competencies['b'], $global));
        $this->assertCount(1, $this->rule->get_condition_records());
    }

    /**
     * Written in a category, "any course" counts only the ratings given in the category's courses.
     */
    public function test_competency_any_course_is_held_to_the_category(): void {
        global $DB;

        $competencies = $this->create_competencies();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $inside = (int) $this->getDataGenerator()->create_user()->id;
        $outside = (int) $this->getDataGenerator()->create_user()->id;
        foreach ([$inside => 'a1', $outside => 'b'] as $userid => $course) {
            $generator->create_user_competency_course([
                'userid' => $userid,
                'competencyid' => $competencies['systemvisible'],
                'courseid' => $this->courses[$course]->id,
                'grade' => 2,
                'proficiency' => 1,
            ]);
        }
        $config = $this->competency_config($competencies['systemvisible'], competency_proficiency::SOURCE_ANY_COURSE);

        $DB->set_field('course_categories', 'name', 'Cat & co', ['id' => $this->contexts['a']->instanceid]);

        $this->setUser($this->manager);
        $this->rule = $this->save_with('competency_proficiency', $config);
        $this->assertSame([$inside], $this->matched());
        $saved = condition_base::get_instance(0, array_values($this->rule->get_condition_records())[0]->to_record());
        $this->assertStringContainsString('rated in any course of the category "Cat &amp; co"', $saved->get_config_description());
        $this->assertStringNotContainsString('&amp;amp;', $saved->get_config_description());

        // The control: written by an administrator, every course counts.
        $this->setAdminUser();
        $this->rule = $this->save_with('competency_proficiency', $config);
        $this->assertSame([$inside, $outside], $this->matched());
    }

    /**
     * A framework from above hidden after the save breaks the rule while it is processed.
     */
    public function test_hidden_framework_breaks_competency_proficiency(): void {
        $competencies = $this->create_competencies();
        $this->setUser($this->manager);
        $config = $this->competency_config($competencies['systemvisible'], competency_proficiency::SOURCE_ANY_COURSE);
        $this->rule = $this->save_with('competency_proficiency', $config);
        $this->assertFalse($this->rule->is_broken(true));

        $framework = \core_competency\competency::get_record(['id' => $competencies['systemvisible']])->get_framework();
        $framework->set('visible', 0);
        $framework->update();

        $this->assertTrue($this->rule->is_broken(true));
    }

    /**
     * Learning plan templates come from those usable from the category.
     */
    public function test_learning_plan(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $specs = [
            'a' => [$this->contexts['a'], 0],
            'b' => [$this->contexts['b'], 1],
            'systemvisible' => [$this->contexts['system'], 1],
            'systemhidden' => [$this->contexts['system'], 0],
        ];
        $templates = [];
        foreach ($specs as $key => [$context, $visible]) {
            $templates[$key] = (int) $generator->create_template(['contextid' => $context->id, 'visible' => $visible])->get('id');
        }
        $config = fn(string $key) => ['operator' => learning_plan::OPERATOR_HAS, 'templateids' => [$templates[$key]],
            'includeunlinked' => 0, 'statuses' => []];

        $this->setUser($this->manager);
        $this->assert_out_of_scope('learning_plan', $config('b'));
        $this->assert_out_of_scope('learning_plan', $config('systemhidden'));
        $this->assert_out_of_scope('learning_plan', ['templateids' => [$templates['a'], $templates['b']]] + $config('a'));

        // The controls: the category's own template, a visible one from above; the administrator free.
        $this->save_with('learning_plan', $config('a'));
        $this->save_with('learning_plan', $config('systemvisible'));
        $this->setAdminUser();
        $this->save_with('learning_plan', $config('b'));
        $this->assertCount(1, $this->rule->get_condition_records());
    }

    /**
     * The learning plan form offers only the templates usable from the category.
     */
    public function test_learning_plan_form_offers_scoped_templates(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $generator->create_template(['shortname' => 'Template of A', 'contextid' => $this->contexts['a']->id]);
        $generator->create_template(['shortname' => 'Template of B', 'contextid' => $this->contexts['b']->id]);

        $condition = learning_plan::get_instance();
        $options = fn() => array_values((new \ReflectionMethod($condition, 'get_template_options'))->invoke($condition));

        $condition->set_scope($this->contexts['a']);
        $this->assertSame(['Template of A'], $options());

        // The control: unscoped, every template.
        $condition->set_scope(null);
        $this->assertSame(['Template of A', 'Template of B'], $options());
    }

    /**
     * A template from above hidden after the save breaks a learning plan rule while it is processed.
     */
    public function test_hidden_template_breaks_learning_plan(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $generator->create_template(['contextid' => $this->contexts['system']->id, 'visible' => 1]);

        $this->setUser($this->manager);
        $this->rule = $this->save_with('learning_plan', ['operator' => learning_plan::OPERATOR_HAS,
            'templateids' => [(int) $template->get('id')], 'includeunlinked' => 0, 'statuses' => []]);
        $this->assertFalse($this->rule->is_broken(true));

        $template->set('visible', 0);
        $template->update();

        $this->assertTrue($this->rule->is_broken(true));
    }

    /**
     * The condition form labels selected competencies only from frameworks usable from the category.
     *
     * Its fragment takes the selected ids from the client, so an id from elsewhere gets a neutral
     * label, the same whether the competency exists or not.
     */
    public function test_competency_form_labels_only_usable_competencies(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/admin/tool/dynamic_cohorts/lib.php');

        $competencies = $this->create_competencies();
        $frameworkname = fn(string $key) => $DB->get_field_sql(
            'SELECT f.shortname FROM {competency_framework} f JOIN {competency} c ON c.competencyframeworkid = f.id WHERE c.id = ?',
            [$competencies[$key]]
        );
        $render = fn(array $ids) => tool_dynamic_cohorts_output_fragment_condition_form([
            'context' => $this->contexts['a'],
            'classname' => self::NS . 'competency_proficiency',
            'defaults' => json_encode(['configdata' => json_encode(['competencyids' => $ids])]),
        ]);

        $this->setUser($this->manager);
        $html = $render([$competencies['a'], $competencies['b'], 999999]);
        $this->assertStringContainsString($frameworkname('a'), $html);
        $this->assertStringNotContainsString($frameworkname('b'), $html);
        $this->assertStringContainsString(get_string('competencyunlabelled', 'tool_dynamic_cohorts', $competencies['b']), $html);
        // A missing id reads the same as one outside the category.
        $this->assertStringContainsString(get_string('competencyunlabelled', 'tool_dynamic_cohorts', 999999), $html);

        // The control: an administrator gets every label.
        $this->setAdminUser();
        $this->assertStringContainsString($frameworkname('b'), $render([$competencies['b']]));
    }

    /**
     * The condition modal describes "any course" as the category's courses before the save records the scope.
     */
    public function test_competency_modal_describes_the_category(): void {
        $competencies = $this->create_competencies();
        $this->setUser($this->manager);
        $_POST['sesskey'] = sesskey();
        $config = $this->competency_config($competencies['systemvisible'], competency_proficiency::SOURCE_ANY_COURSE);
        $data = json_encode(http_build_query($config + ['id' => 0, 'sortorder' => 0,
            '_qf__tool_dynamic_cohorts_condition_form' => 1, 'sesskey' => sesskey()], '', '&'));

        $classname = self::NS . 'competency_proficiency';
        $result = \tool_dynamic_cohorts\external\condition_form::submit($classname, $data, $this->contexts['a']->id);
        $this->assertStringContainsString('rated in any course of the category', $result['description']);
    }
}

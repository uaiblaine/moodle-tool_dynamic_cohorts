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

namespace tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition;

use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\rule;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for course_completed condition class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_completed::class)]
final class course_completed_test extends \advanced_testcase {
    /**
     * Get condition instance for testing.
     *
     * @param array $configdata Config data to be set.
     * @return condition_base
     */
    protected function get_condition(array $configdata = []): condition_base {
        $condition = condition_base::get_instance(0, (object)[
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_completed',
        ]);
        $condition->set_config_data($configdata);

        return $condition;
    }

    /**
     * Test retrieving of config data.
     */
    public function test_retrieving_configdata(): void {
        $formdata = (object)[
            'courseid' => 1,
            'operator' => 3,
            'timecompleted' => 777777,
            'ruleid' => 1,
            'sortorder' => 0,
        ];

        $actual = $this->get_condition()::retrieve_config_data($formdata);
        $expected = [
            'courseid' => 1,
            'operator' => 3,
            'timecompleted' => 777777,
        ];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test setting and getting config data.
     */
    public function test_set_and_get_configdata(): void {
        $condition = $this->get_condition([
            'courseid' => 1,
            'operator' => 3,
            'timecompleted' => 777777,
        ]);

        $this->assertEquals(
            [
                'courseid' => 1,
                'operator' => 3,
                'timecompleted' => 777777,
            ],
            $condition->get_config_data()
        );
    }

    /**
     * Test getting config description.
     */
    public function test_config_description(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $now = time();

        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_AFTER,
            'timecompleted' => $now,
        ]);

        $this->assertSame(
            'Users who have completed course "' . $course->fullname . '" after ' . userdate($now),
            $condition->get_config_description(),
        );

        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_BEFORE,
            'timecompleted' => $now,
        ]);

        $this->assertSame(
            'Users who have completed course "' . $course->fullname . '" before ' . userdate($now),
            $condition->get_config_description(),
        );

        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => $now,
        ]);

        $this->assertSame(
            'Users who have completed course "' . $course->fullname . '"  ',
            $condition->get_config_description(),
        );
    }

    /**
     * Test getting rule.
     */
    public function test_get_rule(): void {
        $this->resetAfterTest();

        // Rule is not set.
        $condition = $this->get_condition();
        $this->assertNull($condition->get_rule());

        // Create a rule and set it to an instance.
        $rule = new rule(0, (object)['name' => 'Test rule 1']);
        $rule->save();

        $condition = cohort_membership::get_instance(0, (object)['ruleid' => $rule->get('id')]);
        $this->assertEquals($condition->get_rule()->get('id'), $rule->get('id'));
    }

    /**
     * Test is broken.
     */
    public function test_is_broken_and_broken_description(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $condition = condition_base::get_instance(0, (object)[
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_completed',
        ]);

        $this->assertFalse($condition->is_broken());

        // Invalid course.
        $condition = $this->get_condition([
            'courseid' => 7777,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);
        $this->assertTrue($condition->is_broken());
        $this->assertSame('Missing course', $condition->get_broken_description());

        // Completion is disabled.
        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);
        $this->assertTrue($condition->is_broken());
        $this->assertSame('Completion is disabled for configured course', $condition->get_broken_description());

        // Completion is enabled.
        $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);
        $this->assertFalse($condition->is_broken());
    }

    /**
     * Test getting correct SQL.
     */
    public function test_get_sql_data(): void {
        global $DB;

        $this->resetAfterTest();

        $now = time();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $studentrole->id);

        $completionuser1 = new \completion_completion(['userid' => $user1->id, 'course' => $course->id]);
        $completionuser1->mark_complete($now + WEEKSECS);

        $completionuser2 = new \completion_completion(['userid' => $user2->id, 'course' => $course->id]);
        $completionuser2->mark_complete($now - WEEKSECS);

        $totalusers = $DB->count_records('user');
        $this->assertTrue($totalusers > 2);

        // Any completion. Should get user 1 and user 2.
        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(2, $DB->get_records_sql($sql, $result->get_params()));

        // Before. Should get user 2.
        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_BEFORE,
            'timecompleted' => $now,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(1, $actual);
        $this->assertSame($user2->id, reset($actual)->id);

        // After. Should get user 1.
        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_AFTER,
            'timecompleted' => $now,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(1, $actual);
        $this->assertSame($user1->id, reset($actual)->id);
    }

    /**
     * The join must not filter users on its own, so that a rule using the OR operator
     * still matches on its other conditions.
     *
     * This is a regression test. The join used to be an INNER JOIN, and
     * condition_manager::build_sql_data() concatenates every condition's join
     * unconditionally while combining only the WHERE fragments with the rule's operator —
     * so the join filtered the whole result set regardless of the operator, and a user
     * matching the second condition was dropped for having no {course_completions} row.
     *
     * Mutation check: put the INNER JOIN back and this goes red on the OR assertion while
     * the AND assertion stays green, which is exactly the asymmetry the bug had.
     *
     * @return void
     */
    public function test_condition_is_neutral_in_a_join_under_the_or_operator(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        // Neither user has ever completed anything, so neither has a completion row.
        $matcher = $this->getDataGenerator()->create_user(['username' => 'matcher']);
        $this->getDataGenerator()->create_user(['username' => 'other']);

        $completed = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_ANY,
        ]);

        // A second condition the first user matches and the second does not.
        $profile = condition_base::get_instance(0, (object)[
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile',
        ]);
        $profile->set_config_data([
            'profilefield' => 'username',
            'username_operator' => condition_base::TEXT_IS_EQUAL_TO,
            'username_value' => 'matcher',
        ]);

        $completedsql = $completed->get_sql();
        $profilesql = $profile->get_sql();

        $join = $completedsql->get_join() . ' ' . $profilesql->get_join();
        $params = $completedsql->get_params() + $profilesql->get_params();

        // OR: the username branch alone must be enough.
        $where = '(' . $completedsql->get_where() . ') OR (' . $profilesql->get_where() . ')';
        $actual = $DB->get_records_sql(
            "SELECT DISTINCT u.id FROM {user} u {$join} WHERE {$where}",
            $params
        );
        $this->assertArrayHasKey($matcher->id, $actual);

        /* Control: the same two conditions under AND must still exclude the user, so the
           test cannot pass by the join having become a no-op in both directions. */
        $where = '(' . $completedsql->get_where() . ') AND (' . $profilesql->get_where() . ')';
        $actual = $DB->get_records_sql(
            "SELECT DISTINCT u.id FROM {user} u {$join} WHERE {$where}",
            $params
        );
        $this->assertArrayNotHasKey($matcher->id, $actual);
    }

    /**
     * Test events that the condition is listening to.
     */
    public function test_get_events(): void {
        $this->assertEquals([], $this->get_condition()->get_events());
    }
}

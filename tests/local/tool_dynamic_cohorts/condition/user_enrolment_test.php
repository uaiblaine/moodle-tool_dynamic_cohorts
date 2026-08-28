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

use context_system;
use context_coursecat;
use tool_dynamic_cohorts\condition_base;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for user_enrolment condition class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_enrolment::class)]
final class user_enrolment_test extends \advanced_testcase {
    /**
     * Get condition instance for testing.
     *
     * @param array $configdata Config data to be set.
     * @return condition_base
     */
    protected function get_condition(array $configdata = []): condition_base {
        $condition = condition_base::get_instance(0, (object)[
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_enrolment',
        ]);
        $condition->set_config_data($configdata);

        return $condition;
    }

    /**
     * Test retrieving of config data.
     */
    public function test_retrieving_configdata(): void {
        $formdata = (object)[
            'operator' => 1,
            'roleid' => 1,
            'courseid' => 1,
            'enrolmethod' => 'manual',
            'sortorder' => 0,
        ];

        $actual = $this->get_condition()::retrieve_config_data($formdata);
        $expected = [
            'operator' => 1,
            'roleid' => 1,
            'courseid' => 1,
            'enrolmethod' => 'manual',
        ];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test setting and getting config data.
     */
    public function test_set_and_get_configdata(): void {
        $condition = $this->get_condition([
            'operator' => 1,
            'roleid' => 1,
            'courseid' => 1,
            'enrolmethod' => 'manual',
        ]);

        $this->assertEquals(
            [
                'operator' => 1,
                'roleid' => 1,
                'courseid' => 1,
                'enrolmethod' => 'manual',
            ],
            $condition->get_config_data()
        );
    }

    /**
     * Test getting config description.
     */
    public function test_config_description(): void {
        $this->resetAfterTest();

        $roleid = $this->getDataGenerator()->create_role();
        $roles = get_all_roles();
        $course = $this->getDataGenerator()->create_course();

        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course->id,
        ]);

        $this->assertSame(
            'Users who are enrolled into course "' . $course->fullname . '" (id ' . $course->id . ')'
            . ' with "Any" role using "Any" enrolment method',
            $condition->get_config_description(),
        );

        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course->id,
        ]);

        $this->assertSame(
            'Users who are not enrolled into course "' . $course->fullname . '" (id ' . $course->id . ')'
            . ' with "Any" role using "Any" enrolment method',
            $condition->get_config_description(),
        );

        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course->id,
            'enrolmethod' => 'manual',
        ]);

        $this->assertSame(
            'Users who are enrolled into course "' . $course->fullname . '" (id ' . $course->id . ')'
            . ' with "Any" role using "Manual enrolments" enrolment method',
            $condition->get_config_description(),
        );

        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course->id,
            'enrolmethod' => 'manual',
        ]);

        $this->assertSame(
            'Users who are not enrolled into course "' . $course->fullname . '" (id ' . $course->id . ')'
            . ' with "Any" role using "Manual enrolments" enrolment method',
            $condition->get_config_description(),
        );

        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course->id,
            'enrolmethod' => 'manual',
            'roleid' => $roleid,

        ]);

        $this->assertSame(
            'Users who are enrolled into course "' . $course->fullname . '" (id ' . $course->id . ')'
            . ' with "' . $roles[$roleid]->name . '" role using "Manual enrolments" enrolment method',
            $condition->get_config_description(),
        );

        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course->id,
            'enrolmethod' => 'manual',
            'roleid' => $roleid,
        ]);

        $this->assertSame(
            'Users who are not enrolled into course "' . $course->fullname . '" (id ' . $course->id . ')'
            . ' with "' . $roles[$roleid]->name . '" role using "Manual enrolments" enrolment method',
            $condition->get_config_description(),
        );
    }

    /**
     * Test is broken and description.
     */
    public function test_is_broken_and_broken_description(): void {
        $this->resetAfterTest();

        $roleid = $this->getDataGenerator()->create_role();
        $course = $this->getDataGenerator()->create_course();

        $condition = condition_base::get_instance(0, (object)[
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_enrolment',
        ]);

        $this->assertFalse($condition->is_broken());

        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course->id,
        ]);
        $this->assertFalse($condition->is_broken());

        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course->id,
            'roleid' => $roleid,
        ]);
        $this->assertFalse($condition->is_broken());

        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course->id,
            'roleid' => $roleid,
            'enrolmethod' => 'manual',
        ]);
        $this->assertFalse($condition->is_broken());

        // Invalid course.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => 7777,
            'roleid' => $roleid,
            'enrolmethod' => 'manual',
        ]);
        $this->assertTrue($condition->is_broken());
        $this->assertSame('Missing course', $condition->get_broken_description());

        // Invalid role.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course->id,
            'roleid' => 77777,
            'enrolmethod' => 'manual',
        ]);
        $this->assertTrue($condition->is_broken());
        $this->assertSame('Missing role', $condition->get_broken_description());

        // Invalid enrole method.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course->id,
            'roleid' => $roleid,
            'enrolmethod' => 'broken',
        ]);
        $this->assertTrue($condition->is_broken());
        $this->assertSame('Missing enrolment method broken', $condition->get_broken_description());
    }

    /**
     * Test getting correct SQL when operator "enrolled".
     */
    public function test_get_sql_data_operator_enrolled(): void {
        global $DB;

        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $course2 = $this->getDataGenerator()->create_course(['category' => $category->id]);

        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);

        $manager = $this->getDataGenerator()->create_user();
        $catteacher = $this->getDataGenerator()->create_user();
        $courseteacher = $this->getDataGenerator()->create_user();
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->role_assign($managerrole->id, $manager->id, context_system::instance()->id);
        $this->getDataGenerator()->role_assign($teacherrole->id, $catteacher->id, context_coursecat::instance($category->id)->id);
        $this->getDataGenerator()->enrol_user($courseteacher->id, $course1->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($student1->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course1->id, $studentrole->id);

        // Anyone enrolled in course 1.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course1->id,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(3, $actual);
        $this->assertArrayHasKey($courseteacher->id, $actual);
        $this->assertArrayHasKey($student1->id, $actual);
        $this->assertArrayHasKey($student2->id, $actual);

        // Anyone enrolled in course 1 with student role.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course1->id,
            'roleid' => $studentrole->id,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(2, $actual);
        $this->assertArrayHasKey($student1->id, $actual);
        $this->assertArrayHasKey($student2->id, $actual);

        // Anyone enrolled in course 1 with teacher role.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course1->id,
            'roleid' => $teacherrole->id,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(1, $actual);
        $this->assertArrayHasKey($courseteacher->id, $actual);

        // Anyone enrolled in course 1 with manager role.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course1->id,
            'roleid' => $managerrole->id,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(0, $actual);

        // Anyone enrolled in course 1 with manual enrol.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course1->id,
            'enrolmethod' => 'manual',
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(3, $actual);
        $this->assertArrayHasKey($courseteacher->id, $actual);
        $this->assertArrayHasKey($student1->id, $actual);
        $this->assertArrayHasKey($student2->id, $actual);

        // Anyone enrolled in course 1 with manual enrol and student role.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course1->id,
            'enrolmethod' => 'manual',
            'roleid' => $studentrole->id,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(2, $actual);
        $this->assertArrayHasKey($student1->id, $actual);
        $this->assertArrayHasKey($student2->id, $actual);

        // Anyone enrolled in course 1 with self enrol.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course1->id,
            'enrolmethod' => 'self',
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(0, $actual);

        // Anyone enrolled in course 2.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course2->id,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(0, $actual);

        // Anyone enrolled in course 2 with student role.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course2->id,
            'roleid' => $studentrole->id,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(0, $actual);

        // Anyone enrolled in course 2 with manual method.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course2->id,
            'enrolmethod' => 'manual',
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(0, $actual);

        // Anyone enrolled in course 2 with self method.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course2->id,
            'enrolmethod' => 'self',
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(0, $actual);

        // Anyone enrolled in course 2 with manual method and student role.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course2->id,
            'roleid' => $studentrole->id,
            'enrolmethod' => 'manual',
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(0, $actual);
    }
    /**
     * Test getting correct SQL when operator "not enrolled".
     */
    public function test_get_sql_data_operator_not_enrolled(): void {
        global $DB;

        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $course2 = $this->getDataGenerator()->create_course(['category' => $category->id]);

        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);

        $manager = $this->getDataGenerator()->create_user();
        $catteacher = $this->getDataGenerator()->create_user();
        $courseteacher = $this->getDataGenerator()->create_user();
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        $totalusers = $DB->count_records('user');

        $this->getDataGenerator()->role_assign($managerrole->id, $manager->id, context_system::instance()->id);
        $this->getDataGenerator()->role_assign($teacherrole->id, $catteacher->id, context_coursecat::instance($category->id)->id);
        $this->getDataGenerator()->enrol_user($courseteacher->id, $course1->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($student1->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course1->id, $studentrole->id);

        // Anyone not enrolled in course 1.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course1->id,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount($totalusers - 3, $actual);
        $this->assertArrayNotHasKey($courseteacher->id, $actual);
        $this->assertArrayNotHasKey($student1->id, $actual);
        $this->assertArrayNotHasKey($student2->id, $actual);

        // Anyone enrolled in course 1 with student role.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course1->id,
            'roleid' => $studentrole->id,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount($totalusers - 2, $actual);
        $this->assertArrayNotHasKey($student1->id, $actual);
        $this->assertArrayNotHasKey($student2->id, $actual);

        // Anyone enrolled in course 1 with teacher role.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course1->id,
            'roleid' => $teacherrole->id,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount($totalusers - 1, $actual);
        $this->assertArrayNotHasKey($courseteacher->id, $actual);

        // Anyone enrolled in course 1 with manager role.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course1->id,
            'roleid' => $managerrole->id,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount($totalusers, $actual);

        // Anyone enrolled in course 1 with manual enrol.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course1->id,
            'enrolmethod' => 'manual',
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount($totalusers - 3, $actual);
        $this->assertArrayNotHasKey($courseteacher->id, $actual);
        $this->assertArrayNotHasKey($student1->id, $actual);
        $this->assertArrayNotHasKey($student2->id, $actual);

        // Anyone enrolled in course 1 with manual enrol and student role.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course1->id,
            'enrolmethod' => 'manual',
            'roleid' => $studentrole->id,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount($totalusers - 2, $actual);
        $this->assertArrayNotHasKey($student1->id, $actual);
        $this->assertArrayNotHasKey($student2->id, $actual);

        // Anyone enrolled in course 1 with self enrol.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course1->id,
            'enrolmethod' => 'self',
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount($totalusers, $actual);

        // Anyone enrolled in course 2.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course2->id,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount($totalusers, $actual);

        // Anyone enrolled in course 2 with student role.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course2->id,
            'roleid' => $studentrole->id,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount($totalusers, $actual);

        // Anyone enrolled in course 2 with manual method.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course2->id,
            'enrolmethod' => 'manual',
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount($totalusers, $actual);

        // Anyone enrolled in course 2 with manual method and student role.
        $condition = $this->get_condition([
            'operator' => user_enrolment::OPERATOR_NOT_ENROLLED,
            'courseid' => $course2->id,
            'roleid' => $studentrole->id,
            'enrolmethod' => 'manual',
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount($totalusers, $actual);
    }

    /**
     * Test events that the condition is listening to.
     */
    public function test_get_events(): void {
        $this->assertEquals([
            'core\event\role_assigned',
            'core\event\role_unassigned',
            'core\event\user_enrolment_created',
            'core\event\user_enrolment_updated',
            'core\event\user_enrolment_deleted',
        ], $this->get_condition()->get_events());
    }
}

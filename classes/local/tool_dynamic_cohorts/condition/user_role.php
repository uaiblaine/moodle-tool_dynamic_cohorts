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

use core_course_category;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_sql;
use context_system;
use context_course;
use context_coursecat;

/**
 * Condition based on user's role.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_role extends condition_base {
    /**
     * Operator for user who have role.
     */
    public const OPERATOR_HAVE_ROLE = 0;

    /**
     * Operator for user who do not have role.
     */
    public const OPERATOR_DO_NOT_HAVE_ROLE = 1;

    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:user_role', 'tool_dynamic_cohorts');
    }

    /**
     * Gets a list of all roles.
     *
     * @return array
     */
    protected function get_all_roles(): array {
        $roles = [];
        foreach (role_get_names() as $role) {
            if ($role->archetype === 'guest') {
                continue;
            }
            $roles[$role->id] = $role->localname;
        }

        return $roles;
    }

    /**
     * Gets a list of operators.
     *
     * @return array A list of operators.
     */
    protected function get_operators(): array {
        return [
            self::OPERATOR_HAVE_ROLE => get_string('haverole', 'tool_dynamic_cohorts'),
            self::OPERATOR_DO_NOT_HAVE_ROLE => get_string('donothaverole', 'tool_dynamic_cohorts'),
        ];
    }

    /**
     * Add config form elements.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_add(\MoodleQuickForm $mform): void {
        // Operator.
        $mform->addElement(
            'select',
            'operator',
            get_string('operator', 'tool_dynamic_cohorts'),
            $this->get_operators()
        );

        // Role field.
        $mform->addElement('select', 'roleid', get_string('role'), $this->get_all_roles());

        // Context level selection.
        $mform->addElement(
            'select',
            'contextlevel',
            get_string('context'),
            [
                CONTEXT_SYSTEM => get_string('coresystem'),
                CONTEXT_COURSECAT => get_string('category'),
                CONTEXT_COURSE => get_string('course'),
            ]
        );

        // Course.
        $mform->addElement('course', 'courseid', get_string('course'));
        $mform->setType('courseid', PARAM_INT);
        $mform->hideIf('courseid', 'contextlevel', 'in', [CONTEXT_SYSTEM, CONTEXT_COURSECAT]);

        // Course category.
        $categories = core_course_category::make_categories_list();
        $mform->addElement('autocomplete', 'categoryid', get_string('coursecategory'), $categories);
        $mform->setType('categoryid', PARAM_INT);
        $mform->hideIf('categoryid', 'contextlevel', 'in', [CONTEXT_SYSTEM, CONTEXT_COURSE]);

        $mform->addElement('checkbox', 'includechildren', '', get_string('includechildren', 'tool_dynamic_cohorts'));
        $mform->hideIf('includechildren', 'contextlevel', 'in', [CONTEXT_SYSTEM, CONTEXT_COURSE]);
    }

    /**
     * Validate config form elements.
     *
     * @param array $data Data to validate.
     * @return array
     */
    public function config_form_validate(array $data): array {
        $errors = [];

        if (empty($data['courseid']) && $data['contextlevel'] == CONTEXT_COURSE) {
            $errors['courseid'] = get_string('required');
        }

        if (empty($data['categoryid']) && $data['contextlevel'] == CONTEXT_COURSECAT) {
            $errors['categoryid'] = get_string('required');
        }

        return $errors;
    }

    /**
     * Gets operator.
     *
     * @return int
     */
    protected function get_operator_value(): int {
        return $this->get_config_data()['operator'] ?? self::OPERATOR_HAVE_ROLE;
    }

    /**
     * Gets configured role ID.
     *
     * @return int
     */
    protected function get_roleid_value(): int {
        return $this->get_config_data()['roleid'] ?? 0;
    }

    /**
     * Gets configured contextlevel.
     *
     * @return int
     */
    protected function get_contextlevel_value(): int {
        return $this->get_config_data()['contextlevel'] ?? 0;
    }

    /**
     * Gets configured course ID.
     *
     * @return int
     */
    protected function get_courseid_value(): int {
        return $this->get_config_data()['courseid'] ?? 0;
    }

    /**
     * Gets configured category ID.
     *
     * @return int
     */
    protected function get_categoryid_value(): int {
        return $this->get_config_data()['categoryid'] ?? 0;
    }

    /**
     * Gets configured include children value.
     *
     * @return int
     */
    protected function get_includechildren_value(): int {
        return $this->get_config_data()['includechildren'] ?? 0;
    }

    /**
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {
        global $DB;

        $rolename = $this->get_all_roles()[$this->get_roleid_value()];

        switch ($this->get_contextlevel_value()) {
            case CONTEXT_SYSTEM:
                return get_string('condition:user_role_description_system', 'tool_dynamic_cohorts', (object)[
                    'role' => $rolename,
                    'operator' => $this->get_operators()[$this->get_operator_value()],
                ]);

            case CONTEXT_COURSECAT:
                $children = !empty($this->get_includechildren_value()) ? get_string('includechildren', 'tool_dynamic_cohorts') : '';

                /* Read the name from the table rather than through core_course_category::get(),
                   which is capability-aware and throws 'cannotviewcategory' for a category the
                   current user cannot see. This is a description string, not an access decision —
                   the access decision is the manage capability on the page that renders it — and
                   the CONTEXT_COURSE branch below already resolves its name with a plain DB read.
                   The not-found case never reaches here: get_broken_description() handles a deleted
                   category, and a broken condition never has get_config_description() called. */
                $categoryname = $DB->get_field('course_categories', 'name', ['id' => $this->get_categoryid_value()]);
                $categoryname = format_string($categoryname, true, ['context' => \context_system::instance()]);

                return get_string('condition:user_role_description_category', 'tool_dynamic_cohorts', (object) [
                    'role' => $rolename,
                    'operator' => $this->get_operators()[$this->get_operator_value()],
                    'categoryname' => $categoryname,
                    'categoryid' => $this->get_categoryid_value(),

                ]) . ' ' . $children;

            case CONTEXT_COURSE:
                $coursename = $DB->get_field('course', 'fullname', ['id' => $this->get_courseid_value()]);
                $coursename = format_string($coursename, true, ['context' => \context_system::instance()]);

                return get_string('condition:user_role_description_course', 'tool_dynamic_cohorts', (object) [
                    'role' => $rolename,
                    'operator' => $this->get_operators()[$this->get_operator_value()],
                    'coursename' => $coursename,
                    'courseid' => $this->get_courseid_value(),
                ]);

            default:
                return '';
        }
    }

    /**
     * Human readable description of the broken condition.
     *
     * @return string
     */
    public function get_broken_description(): string {
        global $DB;

        // Missing role.
        if (!$DB->get_record('role', ['id' => $this->get_roleid_value()])) {
            return get_string('missingrole', 'tool_dynamic_cohorts');
        }

        // Missing course.
        if (
            $this->get_contextlevel_value() == CONTEXT_COURSE &&
            !$DB->get_record('course', ['id' => $this->get_courseid_value()])
        ) {
            return get_string('missingcourse', 'tool_dynamic_cohorts');
        }

        // Missing course category.
        if (
            $this->get_contextlevel_value() == CONTEXT_COURSECAT &&
            !$DB->get_record('course_categories', ['id' => $this->get_categoryid_value()])
        ) {
            return get_string('missingcoursecat', 'tool_dynamic_cohorts');
        }

        return parent::get_broken_description();
    }

    /**
     * Gets SQL data for building SQL.
     *
     * @return condition_sql
     */
    public function get_sql(): condition_sql {
        global $DB;

        $sql = new condition_sql('', '1=0', []);

        if (!$this->is_broken()) {
            $roleid = $this->get_roleid_value();
            $roleidparam = condition_sql::generate_param_alias();
            $params[$roleidparam] = $roleid;

            $ratable = condition_sql::generate_table_alias();
            $innerwhere = "$ratable.roleid = :$roleidparam";

            switch ($this->get_contextlevel_value()) {
                case CONTEXT_SYSTEM:
                    $context = context_system::instance();
                    $contextid = $context->id;
                    $contextidparam = condition_sql::generate_param_alias();
                    $params[$contextidparam] = $contextid;
                    $innerwhere .= " AND $ratable.contextid = :$contextidparam";

                    break;
                case CONTEXT_COURSECAT:
                    $context = context_coursecat::instance($this->get_categoryid_value());

                    if ($this->get_includechildren_value()) {
                        /* Seed with the ancestors, exactly as the unchecked branch below
                           and the CONTEXT_COURSE branch do, then ADD the descendants.
                           Seeding with [$context->id] alone made "include children" a
                           strictly NARROWER match than leaving it off: a user holding the
                           role by a system-level assignment matched while the box was
                           clear and stopped matching the moment it was ticked. Since
                           process_rule() deletes members who no longer match, ticking a
                           box that reads as "more" silently removed them from the cohort. */
                        $contextids = $context->get_parent_context_ids(true);
                        $descendantsql = "SELECT ctx.id
                                            FROM {context} ctx
                                           WHERE ctx.path LIKE :pathpattern";
                        $descendants = $DB->get_records_sql($descendantsql, ['pathpattern' => $context->path . '/%']);
                        $contextids = array_merge($contextids, array_keys($descendants));

                        [$contextsql, $cparams] = $DB->get_in_or_equal(
                            $contextids,
                            SQL_PARAMS_NAMED,
                            condition_sql::generate_param_alias()
                        );
                        $params = array_merge($params, $cparams);
                        $innerwhere .= " AND $ratable.contextid $contextsql";
                    } else {
                        [$parentcontexsql, $parentcparams] = $DB->get_in_or_equal(
                            $context->get_parent_context_ids(true),
                            SQL_PARAMS_NAMED,
                            condition_sql::generate_param_alias()
                        );
                        $params = array_merge($params, $parentcparams);
                        $innerwhere .= " AND $ratable.contextid $parentcontexsql";
                    }

                    break;

                case CONTEXT_COURSE:
                    $context = context_course::instance($this->get_courseid_value());

                    [$contextsql, $cparams] = $DB->get_in_or_equal(
                        $context->get_parent_context_ids(true),
                        SQL_PARAMS_NAMED,
                        condition_sql::generate_param_alias()
                    );

                    $params = array_merge($params, $cparams);
                    $innerwhere .= " AND $ratable.contextid $contextsql";

                    break;
            }

            $outertable = condition_sql::generate_table_alias();

            $join = "LEFT JOIN (SELECT {$ratable}.userid
                          FROM {role_assignments} $ratable
                         WHERE $innerwhere) {$outertable}
                      ON u.id = {$outertable}.userid";

            $haverole = $this->get_operator_value() == self::OPERATOR_HAVE_ROLE;
            $where = $haverole ? " $outertable.userid is NOT NULL" : " $outertable.userid is NULL";

            $sql = new condition_sql($join, $where, $params);
        }

        return $sql;
    }

    /**
     * Is condition broken.
     *
     * @return bool
     */
    public function is_broken(): bool {
        global $DB;

        if ($this->get_config_data()) {
            // Check role exists.
            if (!$DB->get_record('role', ['id' => $this->get_roleid_value()])) {
                return true;
            }

            // Check course exists.
            if (
                $this->get_contextlevel_value() == CONTEXT_COURSE &&
                !$DB->get_record('course', ['id' => $this->get_courseid_value()])
            ) {
                return true;
            }

            // Check category exists.
            if (
                $this->get_contextlevel_value() == CONTEXT_COURSECAT &&
                !$DB->get_record('course_categories', ['id' => $this->get_categoryid_value()])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets a list of event classes the condition will be triggered on.
     *
     * @return string[]
     */
    public function get_events(): array {
        return [
            'core\event\role_assigned',
            'core\event\role_unassigned',
        ];
    }
}

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
use tool_dynamic_cohorts\condition_sql;
use context_course;

/**
 * Condition based on user's enrolment.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_enrolment extends condition_base {
    /**
     * Operator for not enrolled users.
     */
    public const OPERATOR_NOT_ENROLLED = 0;

    /**
     * Operator for enrolled users.
     */
    public const OPERATOR_ENROLLED = 1;

    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:user_enrolment', 'tool_dynamic_cohorts');
    }

    /**
     * Gets a list of all roles.
     *
     * @return array
     */
    protected function get_roles(): array {
        $roles = [
            0 => get_string('any'),
        ];
        foreach (role_get_names() as $role) {
            if ($role->archetype === 'guest') {
                continue;
            }
            $roles[$role->id] = $role->localname;
        }

        return $roles;
    }

    /**
     * Returns a list of enrolment methods.
     *
     * @return array
     */
    protected function get_enrolment_methods(): array {
        $enrolmentmethods = ['' => get_string('any')];
        foreach (enrol_get_plugins(false) as $method) {
            $name = $method->get_name();
            $enrolmentmethods[$name] = get_string('pluginname', 'enrol_' . $name);
        }

        return $enrolmentmethods;
    }

    /**
     * Gets a list of operators.
     *
     * @return array A list of operators.
     */
    protected function get_operators(): array {
        return [
            self::OPERATOR_ENROLLED => get_string('enrolled', 'tool_dynamic_cohorts'),
            self::OPERATOR_NOT_ENROLLED => get_string('notenrolled', 'tool_dynamic_cohorts'),
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

        // Course.
        $mform->addElement('course', 'courseid', get_string('course'));
        $mform->setType('courseid', PARAM_INT);
        $mform->hideIf('courseid', 'contextlevel', 'in', [CONTEXT_SYSTEM, CONTEXT_COURSECAT]);

        // Enrolment method.
        $mform->addElement(
            'select',
            'enrolmethod',
            get_string('enrolmethod', 'tool_dynamic_cohorts'),
            $this->get_enrolment_methods()
        );
        $mform->setType('enrolmethod', PARAM_COMPONENT);

        // Role.
        $mform->addElement('select', 'roleid', get_string('role'), $this->get_roles());
        $mform->setType('roleid', PARAM_INT);
    }

    /**
     * Validate config form elements.
     *
     * @param array $data Data to validate.
     * @return array
     */
    public function config_form_validate(array $data): array {
        $errors = [];

        if (empty($data['courseid'])) {
            $errors['courseid'] = get_string('required');
        }

        return $errors;
    }

    /**
     * Gets operator.
     *
     * @return int
     */
    protected function get_operator_value(): int {
        return $this->get_config_data()['operator'] ?? self::OPERATOR_ENROLLED;
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
     * Gets configured enrolment method.
     *
     * @return string
     */
    protected function get_enrolment_method_value(): string {
        return $this->get_config_data()['enrolmethod'] ?? '';
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
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {
        global $DB;

        $coursename = $DB->get_field('course', 'fullname', ['id' => $this->get_courseid_value()]);
        /* No 'escape' => false here: the description lands in a Mustache TRIPLE stash
           ({{{description}}} in templates/conditions.mustache), which renders raw, so it
           needs the ESCAPED spelling. With escape off, format_string returns bare
           strip_tags() output — a name containing "&" reaches the page as the start of an
           entity, and with formatstringstriptags off it carries purifier-permitted markup
           straight into the table. */
        $coursename = format_string($coursename, true, ['context' => \context_system::instance()]);

        return get_string('condition:user_enrolment_description', 'tool_dynamic_cohorts', (object) [
            'operator' => strtolower($this->get_operators()[$this->get_operator_value()]),
            'role' => $this->get_roles()[$this->get_roleid_value()],
            'coursename' => $coursename,
            'courseid' => $this->get_courseid_value(),
            'enrolmethod' => $this->get_enrolment_methods()[$this->get_enrolment_method_value()],
        ]);
    }

    /**
     * Human-readable description of the broken condition.
     *
     * @return string
     */
    public function get_broken_description(): string {
        global $DB;

        // Missing role.
        if (!array_key_exists($this->get_roleid_value(), $this->get_roles())) {
            return get_string('missingrole', 'tool_dynamic_cohorts');
        }

        // Missing course.
        if (!$DB->get_record('course', ['id' => $this->get_courseid_value()])) {
            return get_string('missingcourse', 'tool_dynamic_cohorts');
        }

        // Missing enrolment method.
        if (!array_key_exists($this->get_enrolment_method_value(), $this->get_enrolment_methods())) {
            return get_string('missingenrolmentmethod', 'tool_dynamic_cohorts', $this->get_enrolment_method_value());
        }

        return parent::get_broken_description();
    }

    /**
     * Gets SQL data for building SQL.
     *
     * @return condition_sql
     */
    public function get_sql(): condition_sql {
        $sql = new condition_sql('', '1=0', []);

        if (!$this->is_broken()) {
            $join = '';
            $params = [];

            // Enrolment tables.
            $uetable = condition_sql::generate_table_alias();
            $enroltable = condition_sql::generate_table_alias();

            // Course parameter.
            $courseidparam = condition_sql::generate_param_alias();
            $params[$courseidparam] = $this->get_courseid_value();

            // In case we are filtering by enrolment method.
            $enrolmethodwhere = '';
            $enrolmethod = $this->get_enrolment_method_value();
            if (!empty($enrolmethod)) {
                $enrolmethodparam = condition_sql::generate_param_alias();
                $params[$enrolmethodparam] = $enrolmethod;
                $enrolmethodwhere = "AND $enroltable.enrol = :{$enrolmethodparam}";
            }

            // In case we are filtering by role.
            $rolesql = '';
            $rolewhere = '';
            $roleid = $this->get_roleid_value();
            if (!empty($roleid)) {
                $outertable = condition_sql::generate_table_alias();
                $ratable = condition_sql::generate_table_alias();
                $roleidparam = condition_sql::generate_param_alias();
                $params[$roleidparam] = $roleid;

                $context = context_course::instance($this->get_courseid_value());
                $contexidtparam = condition_sql::generate_param_alias();
                $params[$contexidtparam] = $context->id;

                $rolesql = "LEFT JOIN (SELECT $ratable.userid
		                                 FROM {role_assignments} $ratable
		                                WHERE $ratable.roleid = :{$roleidparam}
		                                      AND $ratable.contextid = :{$contexidtparam}
		                               ) {$outertable} ON {$uetable}.userid = {$outertable}.userid ";

                $rolewhere = "AND {$outertable}.userid is NOT NULL";
            }

            $operator = $this->get_operator_value() == self::OPERATOR_ENROLLED ? 'EXISTS' : 'NOT EXISTS';

            $where = "{$operator} (SELECT 1 FROM {user_enrolments} {$uetable}
	                         JOIN {enrol} {$enroltable} ON ({$enroltable}.id = {$uetable}.enrolid AND {$enroltable}.status = 0)
                         $rolesql
		                    WHERE {$uetable}.userid = u.id
		                          AND $enroltable.courseid = :{$courseidparam}
		                          AND {$uetable}.status = 0
		                          $enrolmethodwhere
		                          $rolewhere)";

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
            if (!array_key_exists($this->get_roleid_value(), $this->get_roles())) {
                return true;
            }

            // Check course exists.
            if (!$DB->get_record('course', ['id' => $this->get_courseid_value()])) {
                return true;
            }

            // Check enrolment method exists.
            if (!array_key_exists($this->get_enrolment_method_value(), $this->get_enrolment_methods())) {
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
            'core\event\user_enrolment_created',
            'core\event\user_enrolment_updated',
            'core\event\user_enrolment_deleted',
        ];
    }
}

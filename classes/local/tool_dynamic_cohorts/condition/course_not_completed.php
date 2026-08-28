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

use tool_dynamic_cohorts\condition_sql;

/**
 * Condition based on course completion.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_not_completed extends course_completed {
    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:course_not_completed', 'tool_dynamic_cohorts');
    }

    /**
     * Add config form elements.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_add(\MoodleQuickForm $mform): void {
        $mform->addElement('course', 'courseid', get_string('course'), ['onlywithcompletion' => true]);
        $mform->addRule('courseid', null, 'required', null, 'client');
        $mform->setType('courseid', PARAM_INT);
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

        return get_string('condition:course_not_completed_description', 'tool_dynamic_cohorts', (object)[
            'course' => $coursename,
        ]);
    }

    /**
     * Gets SQL data for building SQL.
     *
     * @return condition_sql
     */
    public function get_sql(): condition_sql {
        $sql = new condition_sql('', '1=0', []);

        if (!$this->is_broken()) {
            $params = [];

            $completiontable = condition_sql::generate_table_alias();

            $courseid = $this->get_courseid_value();
            $courseidparam = condition_sql::generate_param_alias();
            $params[$courseidparam] = $courseid;

            $join = "LEFT JOIN {course_completions} $completiontable
                            ON ($completiontable.userid = u.id AND $completiontable.course = :$courseidparam)";

            $where = "$completiontable.timecompleted IS NULL OR $completiontable.id IS NULL";

            $sql = new condition_sql($join, $where, $params);
        }

        return $sql;
    }
}

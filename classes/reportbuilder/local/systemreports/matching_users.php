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

namespace tool_dynamic_cohorts\reportbuilder\local\systemreports;

use core_reportbuilder\local\entities\user;
use core_reportbuilder\system_report;
use tool_dynamic_cohorts\condition_manager;
use tool_dynamic_cohorts\local\rule_access;
use tool_dynamic_cohorts\rule;

/**
 * List of user matching a rule.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matching_users extends system_report {
    /**
     * Initialise the report.
     *
     * @return void
     */
    protected function initialise(): void {
        $ruleid = $this->get_parameter('ruleid', 0, PARAM_INT);
        // Not MUST_EXIST: core runs initialise() before can_view(), and a missing rule must be
        // refused like a rule out of reach, not answered with a different exception.
        $rule = rule::get_record(['id' => $ruleid]) ?: null;

        $userentity = new user();
        $userentity->set_table_alias('user', 'u');
        $usertablealias = $userentity->get_table_alias('user');
        $this->set_main_table('user', $usertablealias);
        $this->add_entity($userentity);

        $conditions = $rule ? $rule->get_condition_records() : [];
        $sql = condition_manager::build_sql_data($conditions, $rule ? $rule->get('operator') : 0);

        if (empty($conditions) || $rule->is_broken()) {
            // No conditions. Filter out all users.
            $this->add_base_condition_sql(' true = false');
        } else {
            $this->add_base_condition_sql($sql->get_where(), $sql->get_params());
        }

        $this->add_base_fields('DISTINCT u.id');
        $this->add_join($sql->get_join());

        $this->add_column_from_entity('user:fullnamewithlink');
        $this->add_column_from_entity('user:username');
        $this->add_column_from_entity('user:email');
        $this->add_column_from_entity('user:idnumber');

        $this->set_initial_sort_column('user:fullnamewithlink', SORT_ASC);

        $this->add_filter_from_entity('user:fullname');
        $this->add_filter_from_entity('user:email');
        $this->add_filter_from_entity('user:idnumber');

        $this->set_downloadable(true);
    }

    /**
     * Check if can view this system report.
     *
     * Decided on the rule the report lists users for, in its cohort's context, not on the
     * context the report was created in: a download request carries both, and only the rule
     * says whose members these are.
     *
     * @return bool
     */
    protected function can_view(): bool {
        $rule = rule::get_record(['id' => $this->get_parameter('ruleid', 0, PARAM_INT)]) ?: null;
        return rule_access::can_manage(rule_access::context_for($rule));
    }
}

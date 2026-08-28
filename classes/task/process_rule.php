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

namespace tool_dynamic_cohorts\task;

use core\task\adhoc_task;
use tool_dynamic_cohorts\rule_manager;
use tool_dynamic_cohorts\rule;

/**
 * Processing a single rule.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_rule extends adhoc_task {
    /**
     * Task name.
     *
     * Without this, adhoc_task's fallback returns the raw class name ("Process rule") in
     * English for every language. It surfaces on admin/tasklogs.php as the log page title
     * and in the failed-task admin notification, which force_current_language()s first.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_process_rule', 'tool_dynamic_cohorts');
    }

    /**
     * Task execution.
     *
     * @return void
     */
    public function execute() {
        $ruleid = (int) $this->get_custom_data();

        /* rule::get_record() returns FALSE for a missing record, it does not throw: the
           default strictness is IGNORE_MISSING (lib/classes/persistent.php). A rule
           deleted between this task being queued and running therefore reached
           process_rule(false), raising a TypeError — an \Error, not an \Exception, so
           the old catch here could never have seen it either. The task then failed and
           was requeued with backoff, forever, over a rule that no longer exists. */
        $rule = rule::get_record(['id' => $ruleid]);

        if (empty($rule)) {
            mtrace("Processing dynamic cohort rules: rule with ID {$ruleid} is not found.");
            return;
        }

        mtrace("Processing dynamic cohort rules: processing rule with id {$ruleid}");
        rule_manager::process_rule($rule);
    }
}

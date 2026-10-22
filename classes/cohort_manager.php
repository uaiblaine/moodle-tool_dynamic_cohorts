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

namespace tool_dynamic_cohorts;

use moodle_exception;
use tool_dynamic_cohorts\local\rule_access;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Cohort manager class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort_manager {
    /**
     * Cohort component.
     */
    const COHORT_COMPONENT = 'tool_dynamic_cohorts';

    /**
     * Get a list of all cohort names in the system keyed by cohort ID.
     *
     * @param bool $excludemanaged Exclude cohorts managed by us.
     * @param \core\context|null $within Only cohorts in this context or below it that the current
     *     user may attach a rule to (rule_access); null for every cohort, unchecked.
     * @return array
     */
    public static function get_cohorts(bool $excludemanaged = false, ?\core\context $within = null): array {
        global $DB;

        /* Read straight from {cohort}, not through cohort_get_all_cohorts(): that one drops the
           cohorts of every context where the CURRENT user lacks moodle/cohort:view or :manage, so a
           category manager holding only moodle/cohort:assign got an empty list, and manage_cohort()
           / unmanage_cohort() below silently did nothing. Who may act on a cohort is rule_access's
           decision, applied when $within is given. */
        $cohorts = [];
        foreach ($DB->get_records('cohort', null, 'name ASC, idnumber ASC') as $cohort) {
            if (!empty($cohort->component) && ($excludemanaged || $cohort->component !== self::COHORT_COMPONENT)) {
                continue;
            }

            if ($within !== null) {
                $context = \core\context::instance_by_id((int) $cohort->contextid, IGNORE_MISSING);
                if (!$context) {
                    continue;
                }
                $inside = $context->id == $within->id || str_starts_with($context->path, $within->path . '/');
                if (!$inside || !rule_access::can_manage($context)) {
                    continue;
                }
            }

            $cohorts[$cohort->id] = $cohort;
        }

        return $cohorts;
    }

    /**
     * Set cohort to be managed by tool_dynamic_cohorts.
     *
     * @param int $cohortid Cohort ID.
     */
    public static function manage_cohort(int $cohortid): void {
        $cohorts = self::get_cohorts();
        if (!empty($cohorts[$cohortid])) {
            $cohort = $cohorts[$cohortid];

            if ($cohort->component === self::COHORT_COMPONENT) {
                throw new moodle_exception('Cohort ' . $cohortid . ' is already managed by tool_dynamic_cohorts');
            }

            $cohort->component = 'tool_dynamic_cohorts';
            cohort_update_cohort($cohort);
        }
    }

    /**
     * Unset cohort from being managed by tool_dynamic_cohorts.
     *
     * @param int $cohortid Cohort ID.
     */
    public static function unmanage_cohort(int $cohortid): void {
        global $DB;

        $cohorts = self::get_cohorts();

        if (!empty($cohorts[$cohortid])) {
            $cohort = $cohorts[$cohortid];
            $cohort->component = '';
            cohort_update_cohort($cohort);

            // Brutally delete members if configured to do so. No cohort_member_removed events will be triggered.
            if (get_config('tool_dynamic_cohorts', 'releasemembers')) {
                $DB->delete_records('cohort_members', ['cohortid' => $cohortid]);
            }
        }
    }
}

<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Callbacks.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_reportbuilder\system_report_factory;
use tool_dynamic_cohorts\condition_form;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\reportbuilder\local\systemreports\matching_users;

/**
 * A new condition form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function tool_dynamic_cohorts_output_fragment_condition_form(array $args): string {
    $args = (object) $args;

    /* core_get_fragment does no access control beyond validate_context() — its own
       docblock says "callbacks that are called by this web service are responsible for
       doing the appropriate security checks" (lib/external/externallib.php). On a system
       context that reduces to require_login(), so every authenticated user could reach
       this callback. The condition forms it renders enumerate cohort names and ids
       (cohort_membership), role names, courses and enrolment methods (user_role,
       user_enrolment) and custom profile field menu options (user_custom_profile) —
       including entries the caller has no business seeing. The sibling matching_users
       fragment is covered by the system report's own can_view(); this one had nothing. */
    require_capability('tool/dynamic_cohorts:manage', context_system::instance());

    $classname = clean_param($args->classname, PARAM_RAW);

    $ajaxdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $ajaxdata);
    }

    $mform = new condition_form(null, ['classname' => $classname], 'post', '', null, true, $ajaxdata);

    unset($ajaxdata['classname']);

    if (!empty($args->defaults)) {
        $data = json_decode($args->defaults, true);
        if (!empty($data)) {
            $confifdata = json_decode($data['configdata']);
            $data = $data + (array)$confifdata;
            $mform->set_data($data);
        }
    }

    if (!empty($ajaxdata)) {
        $mform->is_validated();
    }

    return $mform->render();
}

/**
 * A matching users table as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function tool_dynamic_cohorts_output_fragment_matching_users(array $args): string {
    $args = (object) $args;

    // The system report below enforces this too, via its own can_view(). Stated here as
    // well so the callback does not depend on a check that lives in another class.
    require_capability('tool/dynamic_cohorts:manage', context_system::instance());

    $ruleid = clean_param($args->ruleid, PARAM_INT);

    $rule = rule::get_record(['id' => $ruleid]);
    if (empty($rule)) {
        throw new dml_missing_record_exception(null);
    }

    $report = system_report_factory::create(
        matching_users::class,
        context_system::instance(),
        'tool_dynamic_cohorts',
        '',
        0,
        ['ruleid' => $ruleid]
    );

    return $report->output();
}

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

namespace tool_dynamic_cohorts\external;

use moodle_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_form as form;
use tool_dynamic_cohorts\local\rule_access;

/**
 * Condition form AJAX submission.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition_form extends external_api {
    /**
     * Describes the parameters for validate_form webservice.
     * @return external_function_parameters
     */
    public static function submit_parameters(): external_function_parameters {
        return new external_function_parameters([
            'classname' => new external_value(PARAM_RAW, 'The condition class being submitted'),
            'jsonformdata' => new external_value(PARAM_RAW, 'The data from the form, encoded as a json array'),
            'contextid' => new external_value(
                PARAM_INT,
                'System or course category context the rule is edited from; 0 for the system context',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Submits a form via AJAX.
     *
     * @param string $classname Condition class name.
     * @param string $jsonformdata The data from the form, encoded as a json array.
     * @param int $contextid System or course category context the rule is edited from, 0 for the system.
     * @return array
     */
    public static function submit(string $classname, string $jsonformdata, int $contextid = 0): array {
        $params = self::validate_parameters(
            self::submit_parameters(),
            ['classname' => $classname, 'jsonformdata' => $jsonformdata, 'contextid' => $contextid]
        );

        /* Only the page the condition modal opens from is known here: the rule's cohort can still
           change before the rule is saved, and rule_manager::process_form() decides on that. */
        $context = rule_access::context_from_id($params['contextid']);
        self::validate_context($context);
        rule_access::require_manage($context);

        $ajaxdata = [];
        if (!empty($params['jsonformdata'])) {
            $serialiseddata = json_decode($params['jsonformdata']);
            parse_str($serialiseddata, $ajaxdata);
        }

        $customdata = ['classname' => $classname, 'scope' => rule_access::condition_scope($context)];
        $mform = new form(null, $customdata, 'post', '', null, true, $ajaxdata);
        if (!$mform->is_validated()) {
            throw new moodle_exception('invaliddata');
        }

        $formdata = $mform->get_data();

        $condition = condition_base::get_instance((int)$formdata->id, (object)['classname' => $classname]);
        $condition->set_config_data($condition::retrieve_config_data($formdata));

        return [
            'id' => (int)$formdata->id,
            'sortorder' => (int)$formdata->sortorder,
            'classname' => $classname,
            'configdata' => json_encode($condition->get_config_data()),
            'description' => $condition->is_broken() ? $condition->get_broken_description() : $condition->get_config_description(),
            'name' => $condition->get_name(),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function submit_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, ''),
            'sortorder' => new external_value(PARAM_INT, ''),
            'classname' => new external_value(PARAM_RAW, ''),
            'configdata' => new external_value(PARAM_RAW, ''),
            'description' => new external_value(PARAM_RAW, ''),
            'name' => new external_value(PARAM_RAW, ''),
        ]);
    }
}

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

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;
use core_external\external_single_structure;
use tool_dynamic_cohorts\event\rule_updated;
use tool_dynamic_cohorts\rule;
use invalid_parameter_exception;
use tool_dynamic_cohorts\rule_manager;

/**
 * Rules external APIs.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rules extends external_api {
    /**
     * Describes the parameters for delete_rule webservice.
     *
     * @return external_function_parameters
     */
    public static function delete_rules_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'ruleids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Rule IDs'),
                    'List of rule ids to delete.'
                ),
            ]
        );
    }

    /**
     * Deletes provided rules.
     *
     * @param array $ruleids Rule IDs.
     * @return array
     */
    public static function delete_rules(array $ruleids): array {
        self::validate_parameters(self::delete_rules_parameters(), ['ruleids' => $ruleids]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('tool/dynamic_cohorts:manage', $context);

        // We would like to treat deletion for multiple rules as one operation.
        // So let's check that all rules exist and then delete them.
        // Otherwise throw an exception and fail whole WS call.
        $rulestodelete = [];
        foreach ($ruleids as $ruleid) {
            $rule = rule::get_record(['id' => (int) $ruleid]);
            if (empty($rule)) {
                throw new invalid_parameter_exception('Rule does not exist. ID: ' . $ruleid);
            }
            $rulestodelete[] = $rule;
        }

        foreach ($rulestodelete as $rule) {
            rule_manager::delete_rule($rule);
        }

        return [];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function delete_rules_returns(): external_single_structure {
        return new external_single_structure([]);
    }

    /**
     * Enable or disable rule.
     *
     * @return external_function_parameters
     */
    public static function toggle_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ruleid' => new external_value(PARAM_INT, 'The rule ID to toggle'),
        ]);
    }

    /**
     * Toggle rule.
     *
     * @param int $ruleid Rule ID.
     * @return array
     */
    public static function toggle_status(int $ruleid): array {
        self::validate_parameters(self::toggle_status_parameters(), ['ruleid' => $ruleid]);

        self::validate_context(context_system::instance());
        require_capability('tool/dynamic_cohorts:manage', context_system::instance());

        $rule = rule::get_record(['id' => $ruleid]);
        if (empty($rule)) {
            throw new invalid_parameter_exception('Rule does not exist. ID: ' . $ruleid);
        }

        if ($rule->is_broken()) {
            // Disable broken rule.
            $rule->set('enabled', 0);
            $rule->save();
            rule_updated::create(['other' => ['ruleid' => $rule->get('id')]])->trigger();

            /* The lang string, not a hardcoded English literal: this message reaches the
               user through Notification.exception in amd/src/manage_rules.js. */
            throw new invalid_parameter_exception(
                get_string('cannotenablebrokenrule', 'tool_dynamic_cohorts') . ' ID: ' . $ruleid
            );
        }

        $newvalue = (int) !$rule->is_enabled();
        $rule->set('enabled', $newvalue);
        $rule->save();
        rule_updated::create(['other' => ['ruleid' => $rule->get('id')]])->trigger();

        return [
            'ruleid' => $rule->get('id'),
            'enabled' => $newvalue,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function toggle_status_returns(): external_single_structure {
        return new external_single_structure([
            'ruleid' => new external_value(PARAM_INT, 'The rule ID'),
            'enabled' => new external_value(PARAM_INT, 'New status for the rule'),
        ]);
    }
}

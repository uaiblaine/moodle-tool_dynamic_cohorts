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

/**
 * Condition based on user created date.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_created extends user_last_login {
    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:user_created', 'tool_dynamic_cohorts');
    }

    /**
     * Gets a list of operators.
     *
     * @return array A list of operators.
     */
    protected function get_operators(): array {
        return [
            self::OPERATOR_IN_LAST => get_string('inlast', 'tool_dynamic_cohorts'),
            self::OPERATOR_BEFORE => get_string('before', 'tool_dynamic_cohorts'),
            self::OPERATOR_AFTER => get_string('after', 'tool_dynamic_cohorts'),
        ];
    }

    /**
     * Gets the operator form label.
     *
     * @return string The operator form label.
     */
    protected function get_operator_form_label(): string {
        return get_string('usercreated', 'tool_dynamic_cohorts');
    }

    /**
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {
        $description = '';

        switch ($this->get_operator_value()) {
            case self::OPERATOR_BEFORE:
            case self::OPERATOR_AFTER:
                   $description = get_string('usercreatedtime', 'tool_dynamic_cohorts', (object)[
                      'operator' => strtolower($this->get_operators()[$this->get_operator_value()]),
                      'time' => userdate($this->get_time_value()),
                   ]);
                break;
            case self::OPERATOR_IN_LAST:
                $description = get_string('usercreatedin', 'tool_dynamic_cohorts', s($this->get_period()));
                break;
        }

        return $description;
    }

    /**
     * Gets DB field name to apply filtering on.
     *
     * @return string
     */
    protected function get_db_field(): string {
        return 'timecreated';
    }

    /**
     * Event to trigger condition.
     *
     * @return string[]
     */
    public function get_events(): array {
        return [
            '\core\event\user_created',
        ];
    }
}

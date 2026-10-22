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

/**
 * Condition based on user last login date.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_last_login extends condition_base {
    /**
     * Operator for the condition.
     */
    public const OPERATOR_EVER = 1;

    /**
     * Operator for the condition.
     */
    public const OPERATOR_NEVER = 2;

    /**
     * Operator for the condition.
     */
    public const OPERATOR_BEFORE = 3;

    /**
     * Operator for the condition.
     */
    public const OPERATOR_AFTER = 4;

    /**
     * Operator for the condition.
     */
    public const OPERATOR_IN_LAST = 5;

    /**
     * Available to category-level users: it refers to nothing outside the user (D2).
     *
     * @return bool
     */
    public function supports_category_scope(): bool {
        return true;
    }

    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:user_last_login', 'tool_dynamic_cohorts');
    }

    /**
     * Gets a list of operators.
     *
     * @return array A list of operators.
     */
    protected function get_operators(): array {
        return [
            self::OPERATOR_EVER => get_string('ever', 'tool_dynamic_cohorts'),
            self::OPERATOR_NEVER => get_string('never', 'tool_dynamic_cohorts'),
            self::OPERATOR_BEFORE => get_string('before', 'tool_dynamic_cohorts'),
            self::OPERATOR_AFTER => get_string('after', 'tool_dynamic_cohorts'),
            self::OPERATOR_IN_LAST => get_string('inlast', 'tool_dynamic_cohorts'),
        ];
    }

    /**
     * Gets the operator form label.
     *
     * @return string The operator form label.
     */
    protected function get_operator_form_label(): string {
        return get_string('userlastlogin', 'tool_dynamic_cohorts');
    }

    /**
     * Add config form elements.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_add(\MoodleQuickForm $mform): void {
        $mform->addElement(
            'select',
            'operator',
            $this->get_operator_form_label(),
            $this->get_operators()
        );
        $mform->setType('operator', PARAM_INT);

        $mform->addElement('date_time_selector', 'time');
        $mform->hideIf('time', 'operator', 'in', [self::OPERATOR_EVER, self::OPERATOR_NEVER, self::OPERATOR_IN_LAST]);
        $mform->setDefault('time', usergetmidnight(time()));

        $elements[] = $mform->createElement('text', 'period_value', null, ['size' => 5]);
        $mform->setDefault('period_value', 0);
        $mform->setType('period_value', PARAM_INT);

        $elements[] = $mform->createElement(
            'select',
            'period_type',
            get_string('userlastlogin', 'tool_dynamic_cohorts'),
            $this->get_period_options()
        );

        $mform->addElement('group', 'period', '', $elements, '', false);
        $mform->hideIf('period', 'operator', 'in', [self::OPERATOR_EVER, self::OPERATOR_NEVER,
            self::OPERATOR_BEFORE, self::OPERATOR_AFTER]);
    }

    /**
     * Validate config form elements.
     *
     * @param array $data Data to validate.
     * @return array
     */
    public function config_form_validate(array $data): array {
        $errors = [];

        if ($data['operator'] == self::OPERATOR_IN_LAST && empty($data['period_value'])) {
            $errors['period'] = get_string('required');
        }

        return $errors;
    }

    /**
     * Gets configured time value.
     *
     * @return int
     */
    protected function get_time_value(): int {
        return $this->get_config_data()['time'] ?? 0;
    }

    /**
     * Gets operator value.
     *
     * @return int
     */
    protected function get_operator_value(): int {
        return $this->get_config_data()['operator'] ?? self::OPERATOR_EVER;
    }

    /**
     * Gets configured time value.
     *
     * @return int
     */
    protected function get_period_value_value(): int {
        return $this->get_config_data()['period_value'] ?? 0;
    }

    /**
     * Gets configured time value.
     *
     * @return string
     */
    protected function get_period_type_value(): string {
        return $this->get_config_data()['period_type'] ?? '';
    }

    /**
     * Gets configured time value.
     *
     * @return string
     */
    protected function get_period(): string {
        return $this->get_period_value_value() . ' ' . $this->get_period_type_value();
    }

    /**
     * Gets configured time value.
     *
     * @return string[]
     */
    protected function get_period_options(): array {
        return [
            'hours' => get_string('numhours', 'moodle', ''),
            'days' => get_string('numdays', 'moodle', ''),
            'weeks' => get_string('numweeks', 'moodle', ''),
            'months' => get_string('nummonths', 'moodle', ''),
            'years' => get_string('numyears', 'moodle', ''),
        ];
    }

    /**
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {
        $description = '';

        switch ($this->get_operator_value()) {
            case self::OPERATOR_EVER:
                $description = get_string('everloggedin', 'tool_dynamic_cohorts');
                break;
            case self::OPERATOR_NEVER:
                $description = get_string('neverloggedin', 'tool_dynamic_cohorts');
                break;
            case self::OPERATOR_BEFORE:
            case self::OPERATOR_AFTER:
                   $description = get_string('loggedintime', 'tool_dynamic_cohorts', (object)[
                      'operator' => strtolower($this->get_operators()[$this->get_operator_value()]),
                      'time' => userdate($this->get_time_value()),
                   ]);
                break;
            case self::OPERATOR_IN_LAST:
                $description = get_string('inlastloggedin', 'tool_dynamic_cohorts', $this->get_period());
                break;
        }

        return $description;
    }

    /**
     * Gets SQL data for building SQL.
     *
     * @return condition_sql
     */
    public function get_sql(): condition_sql {
        $sql = new condition_sql('', '1=0', []);

        if (!$this->is_broken()) {
            $dbfield = $this->get_db_field();
            $params = [];
            $where = '';

            $operator = $this->get_operator_value();

            switch ($operator) {
                case self::OPERATOR_EVER:
                    $where = "u.$dbfield > 0";
                    break;
                case self::OPERATOR_NEVER:
                    $where = "u.$dbfield = 0";
                    break;
                case self::OPERATOR_BEFORE:
                case self::OPERATOR_AFTER:
                    $fieldparam = condition_sql::generate_param_alias();
                    $operator = $operator == self::OPERATOR_BEFORE ? '<' : '>';
                    $where = "u.$dbfield > 0 AND u.$dbfield $operator :$fieldparam";
                    $params[$fieldparam] = $this->get_time_value();
                    break;
                case self::OPERATOR_IN_LAST:
                    $fieldparam = condition_sql::generate_param_alias();
                    $where = "u.$dbfield > 0 AND u.$dbfield >= :$fieldparam";
                    $params[$fieldparam] = strtotime('-' . $this->get_period());
                    break;
            }

            $sql = new condition_sql('', $where, $params);
        }

        return $sql;
    }

    /**
     * Gets DB field name to apply filtering on.
     *
     * @return string
     */
    protected function get_db_field(): string {
        return 'lastaccess';
    }

    /**
     * Is condition broken.
     *
     * @return bool
     */
    public function is_broken(): bool {
        if (!empty($this->get_config_data())) {
            $operator = $this->get_operator_value();

            if ($operator == self::OPERATOR_IN_LAST && empty($this->get_period_value_value())) {
                return true;
            }

            if (in_array($operator, [self::OPERATOR_AFTER, self::OPERATOR_BEFORE]) && empty($this->get_time_value())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Event to trigger condition.
     *
     * @return string[]
     */
    public function get_events(): array {
        return [
            '\core\event\user_loggedin',
        ];
    }
}

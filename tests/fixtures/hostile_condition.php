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

namespace tool_dynamic_cohorts\fixtures;

use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_sql;

/**
 * Test condition that misbehaves as its config data says: raw markup in its description, broken,
 * or a \TypeError from get_sql() or is_relevant_event(). It matches the listed users otherwise.
 *
 * Stands for a condition shipped by another plugin, which this plugin cannot make escape or catch.
 * Lives outside classes/, so condition discovery never offers it.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hostile_condition extends condition_base {
    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'Hostile fixture';
    }

    /**
     * No form elements.
     *
     * @param \MoodleQuickForm $mform Form.
     */
    public function config_form_add(\MoodleQuickForm $mform): void {
    }

    /**
     * Nothing to validate.
     *
     * @param array $data Form data.
     * @return array
     */
    public function config_form_validate(array $data): array {
        return [];
    }

    /**
     * Listed users, or a \TypeError when 'throwsql' is set.
     *
     * @return condition_sql
     * @throws \TypeError When the config data says so.
     */
    public function get_sql(): condition_sql {
        global $DB;

        if (!empty($this->get_config_data()['throwsql'])) {
            throw new \TypeError('Hostile fixture get_sql()');
        }
        $userids = $this->get_config_data()['userids'] ?? [];
        if (empty($userids)) {
            return new condition_sql('', '1=0', []);
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, condition_sql::generate_param_alias());
        return new condition_sql('', "u.id {$insql}", $params);
    }

    /**
     * The configured description, unescaped.
     *
     * @return string
     */
    public function get_config_description(): string {
        return (string) ($this->get_config_data()['description'] ?? 'Hostile fixture');
    }

    /**
     * Broken when 'broken' is set; get_broken_description() stays the base one.
     *
     * @return bool
     */
    public function is_broken(): bool {
        return !empty($this->get_config_data()['broken']);
    }

    /**
     * Relevant to every event, or a \TypeError when 'throwrelevant' is set.
     *
     * @param \core\event\base $event The event.
     * @return bool
     * @throws \TypeError When the config data says so.
     */
    public function is_relevant_event(\core\event\base $event): bool {
        if (!empty($this->get_config_data()['throwrelevant'])) {
            throw new \TypeError('Hostile fixture is_relevant_event()');
        }
        return true;
    }
}

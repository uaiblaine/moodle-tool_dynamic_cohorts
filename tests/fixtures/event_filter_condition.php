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
 * Test condition matching listed users, relevant only to events carrying a configured username.
 *
 * Lives outside classes/, so condition discovery never offers it; tests hand it to the observer.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_filter_condition extends condition_base {
    /** @var int Calls of get_sql(): one per condition each time a rule using it is evaluated. */
    public static $sqlcalls = 0;

    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'Event filter fixture';
    }

    /**
     * No form.
     *
     * @param \MoodleQuickForm $mform The form.
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
     * Matches the users listed in config key userids.
     *
     * @return condition_sql
     */
    public function get_sql(): condition_sql {
        global $DB;

        self::$sqlcalls++;
        $userids = $this->get_config_data()['userids'] ?? [];
        if (empty($userids)) {
            return new condition_sql('', '1=0', []);
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, condition_sql::generate_param_alias());
        return new condition_sql('', "u.id {$insql}", $params);
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_config_description(): string {
        return 'Event filter fixture';
    }

    /**
     * Never broken.
     *
     * @return bool
     */
    public function is_broken(): bool {
        return false;
    }

    /**
     * Relevant when no username is configured, or when the event carries the configured one in other.
     *
     * @param \core\event\base $event The event.
     * @return bool
     */
    public function is_relevant_event(\core\event\base $event): bool {
        $username = $this->get_config_data()['relevantusername'] ?? null;
        return $username === null || ($event->other['username'] ?? null) === $username;
    }
}

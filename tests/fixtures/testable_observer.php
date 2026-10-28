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

use tool_dynamic_cohorts\observer;

/**
 * Observer whose condition list is given by the test instead of discovered.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_observer extends observer {
    /**
     * Process an event as if only the given condition classes subscribed to it.
     *
     * @param \core\event\base $event The event.
     * @param array $conditions Unconfigured condition instances.
     */
    public static function process_with(\core\event\base $event, array $conditions): void {
        static::process_event_for_conditions($event, $conditions);
    }
}

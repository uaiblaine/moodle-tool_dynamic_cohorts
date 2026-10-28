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

use core\event\base;

/**
 * Event observer class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /** @var bool[] Per-process memo: condition class => whether it overrides is_relevant_event(). */
    private static $filtersevents = [];

    /**
     * Process event based rules.
     *
     * @param base $event The event.
     */
    public static function process_event(base $event): void {
        // Check if realtime processing enabled globally.
        if (get_config('tool_dynamic_cohorts', 'realtime')) {
            static::process_event_for_conditions($event, condition_manager::get_conditions_with_event($event));
        }
    }

    /**
     * Process the real-time rules that use the given conditions for the user an event is about.
     *
     * A rule is processed at most once per event, however many of its condition classes
     * subscribe to the event, and only when one of its conditions finds the event relevant
     * ({@see condition_base::is_relevant_event()}).
     *
     * @param base $event The event.
     * @param condition_base[] $conditions Unconfigured instances of the condition classes subscribed to the event.
     */
    protected static function process_event_for_conditions(base $event, array $conditions): void {
        /* An event that names no user (fired by a script with no user set) is left to the
           scheduled run: process_rule() reads a user id of 0 as "no user" and would match and
           rewrite the whole cohort inside the request that fired the event. */
        $userid = self::get_userid_from_event($event);
        if ($userid < 1) {
            return;
        }

        $toprocess = [];
        foreach ($conditions as $condition) {
            foreach (rule_manager::get_rules_with_condition($condition) as $rule) {
                $ruleid = (int) $rule->get('id');
                if (isset($toprocess[$ruleid]) || !$rule->is_realtime() || !self::is_relevant_to_rule($condition, $rule, $event)) {
                    continue;
                }
                $toprocess[$ruleid] = $rule;
            }
        }

        foreach ($toprocess as $rule) {
            rule_manager::process_rule($rule, $userid);
        }
    }

    /**
     * Whether any of the rule's conditions of the given class finds the event relevant.
     *
     * A class that keeps the default is_relevant_event() is answered without building its
     * configured instances, so the conditions that do not filter cost nothing more on this path.
     * A stored condition whose class cannot be built counts as relevant: processing then marks the
     * rule broken, as it always has.
     *
     * @param condition_base $condition Unconfigured instance of a subscribed condition class.
     * @param rule $rule A rule using that class.
     * @param base $event The event.
     * @return bool
     */
    private static function is_relevant_to_rule(condition_base $condition, rule $rule, base $event): bool {
        $classname = get_class($condition);
        if (!array_key_exists($classname, self::$filtersevents)) {
            $declaring = (new \ReflectionMethod($classname, 'is_relevant_event'))->getDeclaringClass()->getName();
            self::$filtersevents[$classname] = $declaring !== condition_base::class;
        }
        if (!self::$filtersevents[$classname]) {
            return true;
        }

        foreach ($rule->get_condition_records() as $record) {
            if (ltrim((string) $record->get('classname'), '\\') !== $classname) {
                continue;
            }
            $instance = condition_base::get_instance(0, $record->to_record());
            if (!$instance || $instance->is_relevant_event($event)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets user id from the event.
     *
     * The related user, else the user who acted. Core fills in the related user of an event in a
     * user context that names none ({@see \core\event\base::create()}), so events about a user's
     * own records, such as the competency plan events, name that user.
     *
     * @param \core\event\base $event Triggered event.
     * @return int
     */
    protected static function get_userid_from_event(base $event): int {
        $data = $event->get_data();

        if (array_key_exists('relateduserid', $data) && !empty($data['relateduserid'])) {
            $userid = $data['relateduserid'];
        } else {
            $userid = $data['userid'];
        }

        return $userid;
    }
}

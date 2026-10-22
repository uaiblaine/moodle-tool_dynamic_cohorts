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

namespace tool_dynamic_cohorts\local;

use core\context;
use moodle_exception;
use tool_dynamic_cohorts\rule;

/**
 * The one place that decides who may manage dynamic cohort rules, and in which context.
 *
 * A rule is decided in its cohort's context ({@see rule::get_context()}), so a role held in a
 * course category manages the rules of that category's cohorts. Managing a rule changes the
 * cohort's membership, so the caller needs moodle/cohort:assign there as well as this plugin's
 * capability; neither alone is enough.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_access {
    /** @var array Every capability required to manage rules in a context. */
    private const CAPABILITIES = ['tool/dynamic_cohorts:manage', 'moodle/cohort:assign'];

    /**
     * Whether the current user may manage rules in a context.
     *
     * @param context $context System or course category context.
     * @return bool
     */
    public static function can_manage(context $context): bool {
        foreach (self::CAPABILITIES as $capability) {
            if (!has_capability($capability, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Require the current user to manage rules in a context.
     *
     * @param context $context System or course category context.
     * @throws \required_capability_exception When a capability is missing there.
     */
    public static function require_manage(context $context): void {
        foreach (self::CAPABILITIES as $capability) {
            require_capability($capability, $context);
        }
    }

    /**
     * The context of a cohort, read from {cohort} on every call.
     *
     * A cohort that is gone resolves to the system context, so only site-level managers reach
     * whatever still points at it.
     *
     * @param int $cohortid Cohort id.
     * @return context
     */
    public static function context_for_cohort(int $cohortid): context {
        global $DB;

        $contextid = $DB->get_field('cohort', 'contextid', ['id' => $cohortid]);
        $context = $contextid ? context::instance_by_id((int) $contextid, IGNORE_MISSING) : false;

        return $context ?: \core\context\system::instance();
    }

    /**
     * The context a rule, or a rule id that resolved to nothing, is decided in.
     *
     * A missing rule is decided in the system context, so a caller without the capability
     * there learns nothing about which rule ids exist.
     *
     * @param rule|null $rule The rule, or null when the id did not resolve.
     * @return context
     */
    public static function context_for(?rule $rule): context {
        return $rule ? $rule->get_context() : \core\context\system::instance();
    }

    /**
     * The scope a condition written in a context is held to, or null when it is not held to one.
     *
     * A rule in the system context, or a user who may manage rules site-wide, writes conditions
     * as before; a category-level user writes them within the category.
     *
     * @param context $context The rule's cohort context, or the page's.
     * @return context|null
     */
    public static function condition_scope(context $context): ?context {
        if ($context->contextlevel == CONTEXT_SYSTEM || self::can_manage(\core\context\system::instance())) {
            return null;
        }
        return $context;
    }

    /**
     * Resolve a context id sent by a client into a context rules can be managed in.
     *
     * Cohorts live in the system or a course category context only, so nothing else is accepted.
     *
     * @param int $contextid Context id; 0 means the system context.
     * @return context
     * @throws moodle_exception When the context does not exist or is of another level.
     */
    public static function context_from_id(int $contextid): context {
        if ($contextid === 0) {
            return \core\context\system::instance();
        }

        $context = context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$context || !in_array($context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSECAT])) {
            throw new moodle_exception('invalidcontext');
        }

        return $context;
    }
}

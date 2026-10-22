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
use moodle_url;
use navigation_node;
use tool_dynamic_cohorts\rule;

/**
 * The rules page (index.php) in the system context or in a course category.
 *
 * Kept out of index.php so the access decision and the scope of the listed rules are tested in
 * PHPUnit, where the mutation sweep can see them.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rules_page {
    /** @var string Key of the node in a category's settings navigation. */
    public const NAVIGATION_KEY = 'tool_dynamic_cohorts_rules';

    /**
     * The URL of the rules page for a context.
     *
     * @param context $context System or course category context.
     * @return moodle_url
     */
    public static function url(context $context): moodle_url {
        $params = $context->contextlevel == CONTEXT_COURSECAT ? ['contextid' => $context->id] : [];
        return new moodle_url('/admin/tool/dynamic_cohorts/index.php', $params);
    }

    /**
     * Set up $PAGE for the rules page and require the current user to manage rules there.
     *
     * The system context keeps the site administration page; a course category gets the
     * category's own admin page, as core's cohort/index.php does.
     *
     * @param int $contextid Context id from the request; 0 for the system context.
     * @return context The context the page lists rules for.
     * @throws \moodle_exception When the context is not a system or course category one.
     * @throws \required_capability_exception When the user may not manage rules there.
     */
    public static function setup(int $contextid): context {
        global $CFG, $PAGE;

        $context = rule_access::context_from_id($contextid);

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            require_once($CFG->libdir . '/adminlib.php');
            // The external page only checks the plugin capability; rule_access below adds the rest.
            admin_externalpage_setup('tool_dynamic_cohorts_rules');
        } else {
            require_login();
            $PAGE->set_pagelayout('admin');
            $PAGE->set_context($context);
            $PAGE->set_url(self::url($context));
            \core_course_category::page_setup();
            if ($node = $PAGE->settingsnav->find(self::NAVIGATION_KEY, navigation_node::TYPE_SETTING)) {
                $node->make_active();
            }
            $PAGE->set_title(get_string('managerules', 'tool_dynamic_cohorts'));
        }

        rule_access::require_manage($context);

        return $context;
    }

    /**
     * Rules the page lists in a context: those whose cohort lives in it or below it.
     *
     * The system context lists every rule, including one whose cohort is gone; a course category
     * never lists such a rule, because it is decided in the system context.
     *
     * @param context $context System or course category context.
     * @return rule[]
     */
    public static function rules_in(context $context): array {
        $rules = rule::get_records();
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            return $rules;
        }

        return array_values(array_filter($rules, static function (rule $rule) use ($context): bool {
            $rulecontext = $rule->get_context();
            return $rulecontext->id == $context->id || str_starts_with($rulecontext->path, $context->path . '/');
        }));
    }

    /**
     * Add the rules page to a course category's settings navigation, for those who may use it.
     *
     * @param navigation_node $navigation The category's settings node.
     * @param context $context The category's context.
     */
    public static function extend_category_navigation(navigation_node $navigation, context $context): void {
        if (!rule_access::can_manage($context)) {
            return;
        }

        $navigation->add(
            get_string('categorynavigation', 'tool_dynamic_cohorts'),
            self::url($context),
            navigation_node::TYPE_SETTING,
            null,
            self::NAVIGATION_KEY,
            new \pix_icon('i/cohort', '')
        );
    }
}

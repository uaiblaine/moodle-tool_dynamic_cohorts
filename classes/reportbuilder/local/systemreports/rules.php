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

namespace tool_dynamic_cohorts\reportbuilder\local\systemreports;

use core_cohort\reportbuilder\local\entities\cohort;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\action;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use tool_dynamic_cohorts\local\rule_access;
use tool_dynamic_cohorts\local\rules_page;
use tool_dynamic_cohorts\reportbuilder\local\entities\rule_entity;
use lang_string;
use tool_dynamic_cohorts\rule;
use pix_icon;
use tool_dynamic_cohorts\rule_manager;

/**
 * Rules admin table.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rules extends system_report {
    /**
     * Initialise the report.
     *
     * @return void
     */
    protected function initialise(): void {
        global $DB;

        $ruleentity = new rule_entity();
        $rulealias = $ruleentity->get_table_alias('tool_dynamic_cohorts');
        $this->set_main_table('tool_dynamic_cohorts', $rulealias);
        $this->add_entity($ruleentity);

        $cohortentity = new cohort();
        $cohortalias = $cohortentity->get_table_alias('cohort');
        /* LEFT, so a rule whose cohort was deleted is still listed on the system page, the only
           place it can be managed (rule_access decides it there). */
        $this->add_entity($cohortentity
             ->add_join("LEFT JOIN {cohort} {$cohortalias} ON {$cohortalias}.id = {$rulealias}.cohortid"));

        // Any columns required by actions should be defined here to ensure they're always available.
        $this->add_base_fields("{$rulealias}.id, {$rulealias}.enabled, {$cohortalias}.contextid AS cohortcontextid");

        /* Below the system level, the rules whose cohort lives in the context or below it; never an
           orphan. Any level, not only a category: core's retrieve service lets the client pick the
           report's context, and can_view() refusing other levels must not be the only line. */
        $context = $this->get_context();
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            [$idparam, $pathparam] = database::generate_param_names(2);
            $this->add_base_condition_sql(
                "EXISTS (SELECT 1 FROM {context} rulectx
                          WHERE rulectx.id = {$cohortalias}.contextid
                            AND (rulectx.id = :{$idparam} OR " . $DB->sql_like('rulectx.path', ":{$pathparam}") . "))",
                [$idparam => $context->id, $pathparam => $DB->sql_like_escape($context->path) . '/%']
            );
        }

        $this->add_column_from_entity('rule_entity:name');
        $this->add_column_from_entity('rule_entity:description');
        $this->add_column_from_entity('cohort:name');

        $this->add_column(new column(
            'matchingusers',
            new lang_string('matchingusers', 'tool_dynamic_cohorts'),
            $ruleentity->get_entity_name()
        ))
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(false)
            ->add_fields("{$rulealias}.id")
            ->add_callback(static function ($id): string {
                global $OUTPUT;

                return $OUTPUT->render_from_template('tool_dynamic_cohorts/matching_users', [
                    'ruleid' => $id,
                ]);
            });

        $this->add_column_from_entity('rule_entity:bulkprocessing');
        $this->add_column_from_entity('rule_entity:realtime');

        $this->add_column(new column(
            'conditions',
            new lang_string('conditions', 'tool_dynamic_cohorts'),
            $ruleentity->get_entity_name()
        ))
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(false)
            ->add_fields("{$rulealias}.id, {$rulealias}.operator")
            ->add_callback(static function ($id, $row): string {
                global $OUTPUT;

                $rule = new rule(0, $row);
                $conditions = count($rule->get_condition_records());

                if ($conditions > 0) {
                    /* Rendered from a template, not assembled with html_writer, per the
                       fleet's zero-html_writer rule — and the matchingusers column three
                       above already does exactly this. The template makes it a <button>:
                       this opens the conditions modal (manage_rules.js
                       initRuleConditionsModals), and the <span> it used to be was
                       unreachable by keyboard and announced as nothing. */
                    $conditions = $OUTPUT->render_from_template('tool_dynamic_cohorts/condition_count', [
                        'ruleid' => $rule->get('id'),
                        'count' => $conditions,
                    ]);
                }
                return  get_string('conditionstext', 'tool_dynamic_cohorts', (object)[
                    'conditions' => $conditions,
                    'operator' => rule_manager::get_logical_operator_text($rule->get('operator')),
                ]);
            });

        $this->add_column_from_entity('rule_entity:status');

        $this->add_actions();

        $cohortentity->get_column('name')
            ->set_title(new lang_string('cohort', 'tool_dynamic_cohorts'));

        $this->add_filter_from_entity('rule_entity:name');
        $this->add_filter_from_entity('rule_entity:bulkprocessing');
        $this->add_filter_from_entity('rule_entity:broken');
        $this->add_filter_from_entity('rule_entity:enabled');

        $this->set_initial_sort_column('rule_entity:name', SORT_ASC);
    }

    /**
     * Check if can view this system report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        /* Only the levels the rules page exists at. A course inside a managed category inherits
           both capabilities, and the client chooses the context on core's retrieve service. */
        $context = $this->get_context();
        return in_array($context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSECAT], true)
            && rule_access::can_manage($context);
    }

    /**
     * Add the system report actions. An extra column will be appended to each row, containing all actions added here
     *
     * Note the use of ":id" placeholder which will be substituted according to actual values in the row
     */
    protected function add_actions(): void {
        $this->add_action((new action(
            rules_page::url($this->get_context()),
            new pix_icon('t/hide', '', 'core'),
            ['class' => 'tool-dynamic-cohorts-rule-toggle', 'data-ruleid' => ':id', 'data-action' => 'enable'],
            false,
            new lang_string('enable')
        ))->add_callback(function (\stdClass $row): bool {
            return empty($row->enabled) && self::can_manage_row($row);
        }));

        $this->add_action((new action(
            rules_page::url($this->get_context()),
            new pix_icon('t/show', '', 'core'),
            ['class' => 'tool-dynamic-cohorts-rule-toggle', 'data-ruleid' => ':id', 'data-action' => 'disable'],
            false,
            new lang_string('disable')
        ))->add_callback(function (\stdClass $row): bool {
            return !empty($row->enabled) && self::can_manage_row($row);
        }));

        $this->add_action((new action(
            rules_page::url($this->get_context()),
            new pix_icon('t/edit', '', 'core'),
            ['class' => 'tool-dynamic-cohorts-rule-edit', 'data-ruleid' => ':id'],
            false,
            new lang_string('edit')
        ))->add_callback(fn(\stdClass $row): bool => self::can_manage_row($row)));

        $this->add_action((new action(
            rules_page::url($this->get_context()),
            new pix_icon('t/delete', '', 'core'),
            ['class' => 'tool-dynamic-cohorts-rule-delete', 'data-ruleid' => ':id', 'data-action' => 'delete'],
            false,
            new lang_string('delete')
        ))->add_callback(fn(\stdClass $row): bool => self::can_manage_row($row)));
    }

    /**
     * Whether the current user may manage the rule of a row, in its own cohort's context.
     *
     * The page's context is not enough: a capability prohibited in a subcategory keeps that
     * subcategory's rules listed on the parent's page, but without actions.
     *
     * @param \stdClass $row Report row with the base field cohortcontextid.
     * @return bool
     */
    private static function can_manage_row(\stdClass $row): bool {
        $context = $row->cohortcontextid
            ? \core\context::instance_by_id((int) $row->cohortcontextid, IGNORE_MISSING)
            : false;

        return rule_access::can_manage($context ?: \core\context\system::instance());
    }

    /**
     * CSS class for the row
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row): string {
        $rule = new rule(0, $row);
        return (!$rule->is_enabled()) ? 'text-muted' : '';
    }
}

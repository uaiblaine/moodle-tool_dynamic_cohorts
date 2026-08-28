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

use context;
use context_system;
use core_cohort\reportbuilder\local\entities\cohort;
use core_reportbuilder\local\report\action;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use tool_dynamic_cohorts\reportbuilder\local\entities\rule_entity;
use lang_string;
use moodle_url;
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
        $ruleentity = new rule_entity();
        $rulealias = $ruleentity->get_table_alias('tool_dynamic_cohorts');
        $this->set_main_table('tool_dynamic_cohorts', $rulealias);
        $this->add_entity($ruleentity);

        // Any columns required by actions should be defined here to ensure they're always available.
        $this->add_base_fields("{$rulealias}.id, {$rulealias}.enabled");

        $cohortentity = new cohort();
        $cohortalias = $cohortentity->get_table_alias('cohort');
        $this->add_entity($cohortentity
             ->add_join("JOIN {cohort} {$cohortalias} ON {$cohortalias}.id = {$rulealias}.cohortid"));

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
     * Returns report context.
     *
     * @return \context
     */
    public function get_context(): context {
        return context_system::instance();
    }

    /**
     * Check if can view this system report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('tool/dynamic_cohorts:manage', $this->get_context());
    }

    /**
     * Add the system report actions. An extra column will be appended to each row, containing all actions added here
     *
     * Note the use of ":id" placeholder which will be substituted according to actual values in the row
     */
    protected function add_actions(): void {
        $this->add_action((new action(
            new moodle_url('/admin/tool/dynamic_cohorts/index.php'),
            new pix_icon('t/hide', '', 'core'),
            ['class' => 'tool-dynamic-cohorts-rule-toggle', 'data-ruleid' => ':id', 'data-action' => 'enable'],
            false,
            new lang_string('enable')
        ))->add_callback(function (\stdClass $row): bool {
            return empty($row->enabled);
        }));

        $this->add_action((new action(
            new moodle_url('/admin/tool/dynamic_cohorts/index.php'),
            new pix_icon('t/show', '', 'core'),
            ['class' => 'tool-dynamic-cohorts-rule-toggle', 'data-ruleid' => ':id', 'data-action' => 'disable'],
            false,
            new lang_string('disable')
        ))->add_callback(function (\stdClass $row): bool {
            return !empty($row->enabled);
        }));

        $this->add_action((new action(
            new moodle_url('/admin/tool/dynamic_cohorts/index.php'),
            new pix_icon('t/edit', '', 'core'),
            ['class' => 'tool-dynamic-cohorts-rule-edit', 'data-ruleid' => ':id'],
            false,
            new lang_string('edit')
        )));

        $this->add_action((new action(
            new moodle_url('/admin/tool/dynamic_cohorts/index.php'),
            new pix_icon('t/delete', '', 'core'),
            ['class' => 'tool-dynamic-cohorts-rule-delete', 'data-ruleid' => ':id', 'data-action' => 'delete'],
            false,
            new lang_string('delete')
        )));
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

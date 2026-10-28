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

use core_competency\plan;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_sql;

/**
 * Condition on the learning plans a user holds from given templates, in given statuses.
 *
 * Only plans created from a template count: a plan's template is what a rule manager can name,
 * and individual plans have none. Read from {competency_plan}, never through \core_competency\api,
 * whose getters check the current user's capabilities.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_plan extends condition_base {
    /** The user holds at least one such plan. */
    public const OPERATOR_HAS = 1;

    /** The user holds no such plan. */
    public const OPERATOR_HAS_NOT = 2;

    /** Templates one condition may name. */
    public const MAX_TEMPLATES = 50;

    /** @var array|null Configured template ids that no longer exist, memoised for the config data they were read for. */
    private $missing = null;

    /** @var string|null The config data $missing was read for. */
    private $missingfor = null;

    /** @var \stdClass|false|null The self-synced template, false for none, memoised for the key below. */
    private $selfsynced = null;

    /** @var string|null Rule id and config data $selfsynced was read for. */
    private $selfsyncedfor = null;

    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:learning_plan', 'tool_dynamic_cohorts');
    }

    /**
     * The plan statuses, named as core names them.
     *
     * Not plan::get_status_list(), which offers only the statuses the current user may set.
     *
     * @return array Status => name.
     */
    public static function get_statuses(): array {
        return [
            plan::STATUS_DRAFT => get_string('planstatusdraft', 'core_competency'),
            plan::STATUS_WAITING_FOR_REVIEW => get_string('planstatuswaitingforreview', 'core_competency'),
            plan::STATUS_IN_REVIEW => get_string('planstatusinreview', 'core_competency'),
            plan::STATUS_ACTIVE => get_string('planstatusactive', 'core_competency'),
            plan::STATUS_COMPLETE => get_string('planstatuscomplete', 'core_competency'),
        ];
    }

    /**
     * Templates offered by the form, keyed by id, labelled escaped.
     *
     * @return array
     */
    protected function get_template_options(): array {
        global $DB;

        $where = '';
        $params = [];
        if ($this->scope !== null) {
            [$where, $params] = self::scoped_contexts_sql('t.contextid', 't.visible', $this->scope);
            $where = "WHERE {$where}";
        }

        $options = [];
        $records = $DB->get_records_sql(
            "SELECT t.id, t.shortname, t.contextid FROM {competency_template} t {$where} ORDER BY t.shortname, t.id",
            $params
        );
        foreach ($records as $record) {
            $options[$record->id] = self::format_template_name($record);
        }
        return $options;
    }

    /**
     * Add config form elements.
     *
     * @param \MoodleQuickForm $mform The form.
     */
    public function config_form_add(\MoodleQuickForm $mform): void {
        $mform->addElement('select', 'operator', get_string('learningplanoperator', 'tool_dynamic_cohorts'), [
            self::OPERATOR_HAS => get_string('learningplanoperator_has', 'tool_dynamic_cohorts'),
            self::OPERATOR_HAS_NOT => get_string('learningplanoperator_hasnot', 'tool_dynamic_cohorts'),
        ]);
        $mform->setType('operator', PARAM_INT);

        $mform->addElement(
            'autocomplete',
            'templateids',
            get_string('learningplantemplates', 'tool_dynamic_cohorts'),
            $this->get_template_options(),
            ['multiple' => true]
        );
        $mform->addRule('templateids', null, 'required', null, 'client');
        $mform->setType('templateids', PARAM_INT);

        $mform->addElement('advcheckbox', 'includeunlinked', get_string('learningplanincludeunlinked', 'tool_dynamic_cohorts'));
        $mform->addHelpButton('includeunlinked', 'learningplanincludeunlinked', 'tool_dynamic_cohorts');

        $mform->addElement(
            'autocomplete',
            'statuses',
            get_string('learningplanstatuses', 'tool_dynamic_cohorts'),
            self::get_statuses(),
            ['multiple' => true, 'noselectionstring' => get_string('learningplanstatuses_any', 'tool_dynamic_cohorts')]
        );
        $mform->setType('statuses', PARAM_INT);
        $mform->addHelpButton('statuses', 'learningplanstatuses', 'tool_dynamic_cohorts');
    }

    /**
     * Validate config form elements.
     *
     * @param array $data Data to validate.
     * @return array Errors keyed by element name.
     */
    public function config_form_validate(array $data): array {
        global $DB;

        $errors = [];

        $ids = self::clean_ids($data['templateids'] ?? []);
        if (empty($ids)) {
            $errors['templateids'] = get_string('required');
        } else if (count($ids) > self::MAX_TEMPLATES) {
            $errors['templateids'] = get_string('learningplantemplates_max', 'tool_dynamic_cohorts', self::MAX_TEMPLATES);
        } else {
            [$insql, $params] = $DB->get_in_or_equal($ids);
            if ($DB->count_records_select('competency_template', "id {$insql}", $params) != count($ids)) {
                $errors['templateids'] = get_string('learningplantemplates_missing', 'tool_dynamic_cohorts');
            }
        }

        if (!in_array((int) ($data['operator'] ?? 0), [self::OPERATOR_HAS, self::OPERATOR_HAS_NOT], true)) {
            $errors['operator'] = get_string('required');
        }
        if (array_diff(self::clean_ids($data['statuses'] ?? [], true), array_keys(self::get_statuses()))) {
            $errors['statuses'] = get_string('required');
        }

        return $errors;
    }

    /**
     * Available to category-level users, held to the templates usable from their category (owner decision Q2).
     *
     * @return bool
     */
    public function supports_category_scope(): bool {
        return true;
    }

    /**
     * Rechecked while the rule is processed: a template's category can move and its visibility change.
     *
     * @return bool
     */
    protected function rechecks_scope_when_processed(): bool {
        return true;
    }

    /**
     * Errors for templates not usable from the scope: the scope's own and its subcategories', or a
     * visible one of a parent context ({@see condition_base::scoped_contexts_sql()}).
     *
     * @return array Form errors keyed by element name.
     */
    public function validate_scope(): array {
        global $DB;

        $ids = $this->get_templateids();
        if ($this->scope === null || empty($ids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'scopetpl');
        [$usablesql, $usableparams] = self::scoped_contexts_sql('t.contextid', 't.visible', $this->scope);
        $usable = $DB->count_records_sql(
            "SELECT COUNT(1) FROM {competency_template} t WHERE t.id {$insql} AND {$usablesql}",
            $params + $usableparams
        );

        return $usable == count($ids) ? [] : ['templateids' => get_string('outofscope', 'tool_dynamic_cohorts')];
    }

    /**
     * Gets required config data from submitted condition form data.
     *
     * @param \stdClass $formdata Form data generated via $mform->get_data()
     * @return array
     */
    public static function retrieve_config_data(\stdClass $formdata): array {
        $configdata = parent::retrieve_config_data($formdata);

        $configdata['operator'] = (int) ($configdata['operator'] ?? 0);
        $configdata['templateids'] = self::clean_ids($configdata['templateids'] ?? []);
        $configdata['includeunlinked'] = empty($configdata['includeunlinked']) ? 0 : 1;
        $configdata['statuses'] = self::clean_ids($configdata['statuses'] ?? [], true);

        return $configdata;
    }

    /**
     * Integer ids, without duplicates, in the order given: positive ones, or with $zero also 0 (draft is status 0).
     *
     * Only integers and digit strings count: an empty autocomplete posts '', which intval() would
     * turn into 0, the draft status.
     *
     * @param mixed $ids An id or a list of ids.
     * @param bool $zero Whether 0 is a valid value.
     * @return int[]
     */
    private static function clean_ids($ids, bool $zero = false): array {
        $ids = array_filter(is_array($ids) ? $ids : [$ids], fn($id) => is_int($id) || (is_string($id) && ctype_digit($id)));
        $ids = array_map('intval', $ids);
        return array_values(array_unique(array_filter($ids, fn($id) => $zero ? $id >= 0 : $id > 0)));
    }

    /**
     * Configured template ids.
     *
     * @return int[]
     */
    protected function get_templateids(): array {
        return self::clean_ids($this->get_config_data()['templateids'] ?? []);
    }

    /**
     * Configured statuses; empty for any status.
     *
     * @return int[]
     */
    protected function get_configured_statuses(): array {
        return self::clean_ids($this->get_config_data()['statuses'] ?? [], true);
    }

    /**
     * Whether "has" is configured, rather than "has not".
     *
     * @return bool
     */
    protected function is_has(): bool {
        return (int) ($this->get_config_data()['operator'] ?? self::OPERATOR_HAS) !== self::OPERATOR_HAS_NOT;
    }

    /**
     * Whether plans unlinked from the templates count too.
     *
     * @return bool
     */
    protected function includes_unlinked(): bool {
        return !empty($this->get_config_data()['includeunlinked']);
    }

    /**
     * Configured templates that no longer exist.
     *
     * Read once per config data: build_sql_data() and get_sql() both call is_broken().
     *
     * @return int[]
     */
    protected function get_missing_templateids(): array {
        global $DB;

        $configdata = (string) $this->get_record()->get('configdata');
        if ($this->missingfor !== $configdata) {
            $ids = $this->get_templateids();
            $existing = [];
            if (!empty($ids)) {
                [$insql, $params] = $DB->get_in_or_equal($ids);
                $existing = array_map('intval', $DB->get_fieldset_select('competency_template', 'id', "id {$insql}", $params));
            }
            $this->missing = array_values(array_diff($ids, $existing));
            $this->missingfor = $configdata;
        }

        return $this->missing;
    }

    /**
     * A configured template whose cohort sync fills plans for the rule's own cohort, or null.
     *
     * Core's sync gives every member of a template's cohorts a plan and never takes it away, so a
     * rule on such a template could never remove a member it had added ("has") or take back one it
     * had removed ("has not"). Same shape as cohort_membership's guard on its own cohort.
     *
     * @return \stdClass|null The template's id, shortname and contextid.
     */
    protected function get_self_synced_template(): ?\stdClass {
        global $DB;

        $key = $this->get_record()->get('ruleid') . '|' . $this->get_record()->get('configdata');
        if ($this->selfsyncedfor === $key) {
            return $this->selfsynced ?: null;
        }
        $this->selfsyncedfor = $key;
        $this->selfsynced = false;

        $rule = $this->get_rule();
        $ids = $this->get_templateids();
        if (!$rule || empty($ids)) {
            return null;
        }

        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $params['cohortid'] = (int) $rule->get('cohortid');
        $records = $DB->get_records_sql(
            "SELECT t.id, t.shortname, t.contextid
               FROM {competency_template} t
               JOIN {competency_templatecohort} tc ON tc.templateid = t.id
              WHERE tc.cohortid = :cohortid AND t.id {$insql}
           ORDER BY t.id",
            $params,
            0,
            1
        );

        $this->selfsynced = $records ? reset($records) : false;
        return $this->selfsynced ?: null;
    }

    /**
     * A template's short name, escaped for an HTML sink.
     *
     * @param \stdClass $record With shortname and contextid.
     * @return string
     */
    public static function format_template_name(\stdClass $record): string {
        $context = \core\context::instance_by_id((int) $record->contextid, IGNORE_MISSING) ?: \core\context\system::instance();
        return format_string($record->shortname, true, ['context' => $context]);
    }

    /**
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {
        global $DB;

        $names = [];
        $ids = $this->get_templateids();
        if (!empty($ids)) {
            [$insql, $params] = $DB->get_in_or_equal($ids);
            $records = $DB->get_records_select(
                'competency_template',
                "id {$insql}",
                $params,
                'shortname, id',
                'id, shortname, contextid'
            );
            foreach ($ids as $id) {
                $names[] = isset($records[$id])
                    ? self::format_template_name($records[$id])
                    : get_string('learningplantemplatemissing', 'tool_dynamic_cohorts', $id);
            }
        }

        $statuses = array_intersect_key(self::get_statuses(), array_flip($this->get_configured_statuses()));
        $a = (object) [
            'templates' => implode(', ', $names),
            'statuses' => $statuses ? implode(', ', $statuses) : get_string('learningplanstatuses_any', 'tool_dynamic_cohorts'),
            'unlinked' => $this->includes_unlinked() ? get_string('learningplanunlinked_description', 'tool_dynamic_cohorts') : '',
        ];

        return $this->is_has()
            ? get_string('condition:learning_plan_description_has', 'tool_dynamic_cohorts', $a)
            : get_string('condition:learning_plan_description_hasnot', 'tool_dynamic_cohorts', $a);
    }

    /**
     * Human readable description of the broken condition: what is wrong and how to resume.
     *
     * @return string
     */
    public function get_broken_description(): string {
        if ($this->is_outside_recorded_scope()) {
            return get_string('brokenoutofscope', 'tool_dynamic_cohorts');
        }

        if ($missing = $this->get_missing_templateids()) {
            return get_string('learningplantemplates_broken', 'tool_dynamic_cohorts', implode(', ', $missing));
        }

        if ($template = $this->get_self_synced_template()) {
            return get_string('learningplantemplates_selfsync', 'tool_dynamic_cohorts', self::format_template_name($template));
        }

        return parent::get_broken_description();
    }

    /**
     * Is condition broken.
     *
     * An unconfigured instance is broken while competencies are disabled, so the condition is not
     * offered then; a configured one keeps evaluating the stored plans.
     *
     * @return bool
     */
    public function is_broken(): bool {
        if (!$this->get_config_data()) {
            return !get_config('core_competency', 'enabled');
        }

        if ($this->is_outside_recorded_scope()) {
            return true;
        }

        if (empty($this->get_templateids()) || !empty($this->get_missing_templateids())) {
            return true;
        }

        return $this->get_self_synced_template() !== null;
    }

    /**
     * SQL for the configured condition: an EXISTS on the user's plans, in WHERE with no join.
     *
     * @return condition_sql
     */
    public function get_sql(): condition_sql {
        global $DB;

        if ($this->is_broken()) {
            return new condition_sql('', '1=0', []);
        }

        $alias = condition_sql::generate_table_alias();
        $ids = $this->get_templateids();

        // A named placeholder appears once per statement, so each IN list gets its own parameters.
        [$templatesql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, condition_sql::generate_param_alias());
        $templates = "{$alias}.templateid {$templatesql}";
        if ($this->includes_unlinked()) {
            [$origsql, $origparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, condition_sql::generate_param_alias());
            $templates = "({$templates} OR {$alias}.origtemplateid {$origsql})";
            $params += $origparams;
        }

        $filter = "{$alias}.userid = u.id AND {$templates}";
        if ($statuses = $this->get_configured_statuses()) {
            [$statussql, $statusparams] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED, condition_sql::generate_param_alias());
            $filter .= " AND {$alias}.status {$statussql}";
            $params += $statusparams;
        }

        $where = ($this->is_has() ? '' : 'NOT ') . "EXISTS (SELECT 1 FROM {competency_plan} {$alias} WHERE {$filter})";
        return new condition_sql('', $where, $params);
    }

    /**
     * The plan events that create or delete a plan, change its status, or unlink it from its template.
     *
     * Core names the plan owner as their related user ({@see \core\event\base::create()}).
     *
     * @return string[]
     */
    public function get_events(): array {
        return [
            '\core\event\competency_plan_created',
            '\core\event\competency_plan_deleted',
            '\core\event\competency_plan_approved',
            '\core\event\competency_plan_unapproved',
            '\core\event\competency_plan_review_requested',
            '\core\event\competency_plan_review_request_cancelled',
            '\core\event\competency_plan_review_started',
            '\core\event\competency_plan_review_stopped',
            '\core\event\competency_plan_completed',
            '\core\event\competency_plan_reopened',
            '\core\event\competency_plan_unlinked',
        ];
    }

    /**
     * Relevant when the plan was created from, or unlinked from, a configured template.
     *
     * Both columns are read whether or not unlinked plans count: a plan being unlinked leaves the
     * set of plans "from the template", so its rule must be processed. Every plan event carries the
     * plan as a snapshot; without one, the event counts as relevant.
     *
     * @param \core\event\base $event The event.
     * @return bool
     */
    public function is_relevant_event(\core\event\base $event): bool {
        if ($event->objecttable !== plan::TABLE || empty($event->objectid)) {
            return true;
        }

        $plan = $event->get_record_snapshot(plan::TABLE, $event->objectid);
        if (!$plan) {
            return true;
        }

        $ids = $this->get_templateids();
        return in_array((int) $plan->templateid, $ids, true) || in_array((int) $plan->origtemplateid, $ids, true);
    }
}

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
 * Condition on the proficiency a user holds in one or more competencies.
 *
 * Reads the stored ratings, never \core_competency\api: its getters check the current user's
 * capabilities and throw while competencies are disabled, and the observer and cron run without
 * the right user. A rating keeps counting after its competency is unlinked from a course or
 * competencies are disabled site-wide, as in availability_competency. Every rating is written by
 * \core_competency\api::add_evidence(), which raises competency_evidence_created.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_proficiency extends condition_base {
    /** Proficient in at least one of the competencies (or, not proficient: lacking at least one). */
    public const MATCH_ANY = 1;

    /** Proficient in every one of the competencies (or, not proficient: lacking every one). */
    public const MATCH_ALL = 2;

    /** The user holds a proficient rating. */
    public const STATE_PROFICIENT = 1;

    /** The user holds no proficient rating: never rated, rated without proficiency, or rated not proficient. */
    public const STATE_NOT_PROFICIENT = 2;

    /** The site-wide rating, {competency_usercomp}. */
    public const SOURCE_GLOBAL = 1;

    /** The rating given in one course, {competency_usercompcourse}. */
    public const SOURCE_COURSE = 2;

    /** The rating given in any course, {competency_usercompcourse}. */
    public const SOURCE_ANY_COURSE = 3;

    /** Competencies one condition may name: bounds the IN list and the description. */
    public const MAX_COMPETENCIES = 50;

    /** @var array|null Configured competency ids that no longer exist, memoised for the config data they were read for. */
    private $missing = null;

    /** @var string|null The config data $missing was read for. */
    private $missingfor = null;

    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:competency_proficiency', 'tool_dynamic_cohorts');
    }

    /**
     * Add config form elements.
     *
     * @param \MoodleQuickForm $mform The form.
     */
    public function config_form_add(\MoodleQuickForm $mform): void {
        $mform->addElement('autocomplete', 'competencyids', get_string('competencyids', 'tool_dynamic_cohorts'), [], [
            'multiple' => true,
            'ajax' => 'tool_dynamic_cohorts/competency_selector',
            'noselectionstring' => get_string('competencysearch', 'tool_dynamic_cohorts'),
            'valuehtmlcallback' => fn($id) => self::competency_label((int) $id),
        ]);
        $mform->addRule('competencyids', null, 'required', null, 'client');
        $mform->setType('competencyids', PARAM_INT);

        $mform->addElement('select', 'match', get_string('competencymatch', 'tool_dynamic_cohorts'), [
            self::MATCH_ANY => get_string('competencymatch_any', 'tool_dynamic_cohorts'),
            self::MATCH_ALL => get_string('competencymatch_all', 'tool_dynamic_cohorts'),
        ]);
        $mform->setType('match', PARAM_INT);

        $mform->addElement('select', 'state', get_string('competencystate', 'tool_dynamic_cohorts'), [
            self::STATE_PROFICIENT => get_string('competencystate_proficient', 'tool_dynamic_cohorts'),
            self::STATE_NOT_PROFICIENT => get_string('competencystate_notproficient', 'tool_dynamic_cohorts'),
        ]);
        $mform->setType('state', PARAM_INT);
        $mform->addHelpButton('state', 'competencystate', 'tool_dynamic_cohorts');

        $mform->addElement('select', 'source', get_string('competencysource', 'tool_dynamic_cohorts'), [
            self::SOURCE_GLOBAL => get_string('competencysource_global', 'tool_dynamic_cohorts'),
            self::SOURCE_COURSE => get_string('competencysource_course', 'tool_dynamic_cohorts'),
            self::SOURCE_ANY_COURSE => get_string('competencysource_anycourse', 'tool_dynamic_cohorts'),
        ]);
        $mform->setType('source', PARAM_INT);
        $mform->addHelpButton('source', 'competencysource', 'tool_dynamic_cohorts');

        $this->add_course_element($mform, 'courseid', get_string('course'));
        $mform->setType('courseid', PARAM_INT);
        $mform->hideIf('courseid', 'source', 'neq', self::SOURCE_COURSE);
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

        $ids = self::clean_ids($data['competencyids'] ?? []);
        if (empty($ids)) {
            $errors['competencyids'] = get_string('required');
        } else if (count($ids) > self::MAX_COMPETENCIES) {
            $errors['competencyids'] = get_string('competencyids_max', 'tool_dynamic_cohorts', self::MAX_COMPETENCIES);
        } else {
            [$insql, $params] = $DB->get_in_or_equal($ids);
            if ($DB->count_records_select('competency', "id {$insql}", $params) != count($ids)) {
                $errors['competencyids'] = get_string('competencyids_missing', 'tool_dynamic_cohorts');
            }
        }

        if (!in_array((int) ($data['match'] ?? 0), [self::MATCH_ANY, self::MATCH_ALL], true)) {
            $errors['match'] = get_string('required');
        }
        if (!in_array((int) ($data['state'] ?? 0), [self::STATE_PROFICIENT, self::STATE_NOT_PROFICIENT], true)) {
            $errors['state'] = get_string('required');
        }

        $source = (int) ($data['source'] ?? 0);
        if (!in_array($source, [self::SOURCE_GLOBAL, self::SOURCE_COURSE, self::SOURCE_ANY_COURSE], true)) {
            $errors['source'] = get_string('required');
        } else if ($source === self::SOURCE_COURSE && !$DB->record_exists('course', ['id' => (int) ($data['courseid'] ?? 0)])) {
            $errors['courseid'] = get_string('required');
        }

        return $errors;
    }

    /**
     * Gets required config data from submitted condition form data.
     *
     * Ids are cast and deduplicated, and a course is kept only for the one-course source: the
     * course selector posts its value even while it is hidden.
     *
     * @param \stdClass $formdata Form data generated via $mform->get_data()
     * @return array
     */
    public static function retrieve_config_data(\stdClass $formdata): array {
        $configdata = parent::retrieve_config_data($formdata);

        $configdata['competencyids'] = self::clean_ids($configdata['competencyids'] ?? []);
        foreach (['match', 'state', 'source', 'courseid'] as $key) {
            $configdata[$key] = (int) ($configdata[$key] ?? 0);
        }
        if ($configdata['source'] !== self::SOURCE_COURSE) {
            $configdata['courseid'] = 0;
        }

        return $configdata;
    }

    /**
     * Positive integer ids, without duplicates, in the order given.
     *
     * @param mixed $ids An id or a list of ids.
     * @return int[]
     */
    private static function clean_ids($ids): array {
        $ids = array_map('intval', is_array($ids) ? $ids : [$ids]);
        return array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
    }

    /**
     * Configured competency ids.
     *
     * @return int[]
     */
    protected function get_competencyids(): array {
        return self::clean_ids($this->get_config_data()['competencyids'] ?? []);
    }

    /**
     * Configured value of an integer setting.
     *
     * @param string $key Config key.
     * @param int $default Value when unset.
     * @return int
     */
    protected function get_int(string $key, int $default): int {
        return (int) ($this->get_config_data()[$key] ?? $default);
    }

    /**
     * Configured competencies that no longer exist.
     *
     * Read once per config data: build_sql_data() and get_sql() both call is_broken().
     *
     * @return int[]
     */
    protected function get_missing_competencyids(): array {
        global $DB;

        $configdata = (string) $this->get_record()->get('configdata');
        if ($this->missingfor !== $configdata) {
            $ids = $this->get_competencyids();
            $existing = [];
            if (!empty($ids)) {
                [$insql, $params] = $DB->get_in_or_equal($ids);
                $existing = array_map('intval', $DB->get_fieldset_select('competency', 'id', "id {$insql}", $params));
            }
            $this->missing = array_values(array_diff($ids, $existing));
            $this->missingfor = $configdata;
        }

        return $this->missing;
    }

    /**
     * Label of a competency, "Framework › Competency (idnumber)", escaped for an HTML sink.
     *
     * Read from the tables, as every description here is: core's competency getters check the
     * viewer's capabilities. Escaped, because core's autocomplete inserts labels as HTML.
     *
     * @param int $id Competency id.
     * @return string
     */
    public static function competency_label(int $id): string {
        global $DB;

        $record = $DB->get_record_sql(
            "SELECT c.shortname, c.idnumber, f.shortname AS frameworkname, f.contextid
               FROM {competency} c
               JOIN {competency_framework} f ON f.id = c.competencyframeworkid
              WHERE c.id = :id",
            ['id' => $id]
        );
        if (!$record) {
            return get_string('competencymissing', 'tool_dynamic_cohorts', $id);
        }

        return self::format_label($record);
    }

    /**
     * Format a competency record read with its framework's shortname and context.
     *
     * @param \stdClass $record With shortname, idnumber, frameworkname and contextid.
     * @return string Escaped label.
     */
    public static function format_label(\stdClass $record): string {
        $context = \core\context::instance_by_id((int) $record->contextid, IGNORE_MISSING) ?: \core\context\system::instance();
        $options = ['context' => $context];

        return get_string('competencylabel', 'tool_dynamic_cohorts', (object) [
            'framework' => format_string($record->frameworkname, true, $options),
            'competency' => format_string($record->shortname, true, $options),
            'idnumber' => s($record->idnumber),
        ]);
    }

    /**
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {
        global $DB;

        $labels = array_map(fn($id) => self::competency_label($id), $this->get_competencyids());

        $source = $this->get_int('source', self::SOURCE_GLOBAL);
        if ($source === self::SOURCE_COURSE) {
            $coursename = (string) $DB->get_field('course', 'fullname', ['id' => $this->get_int('courseid', 0)]);
            $coursename = format_string($coursename, true, ['context' => \core\context\system::instance()]);
            $sourcetext = get_string('competencysource_course_description', 'tool_dynamic_cohorts', $coursename);
        } else if ($source === self::SOURCE_ANY_COURSE) {
            $sourcetext = get_string('competencysource_anycourse_description', 'tool_dynamic_cohorts');
        } else {
            $sourcetext = get_string('competencysource_global_description', 'tool_dynamic_cohorts');
        }

        $a = (object) ['competencies' => implode(', ', $labels), 'source' => $sourcetext];
        $all = $this->get_int('match', self::MATCH_ANY) === self::MATCH_ALL;
        $proficient = $this->get_int('state', self::STATE_PROFICIENT) === self::STATE_PROFICIENT;

        return match (true) {
            $proficient && !$all => get_string('condition:competency_proficiency_any_proficient', 'tool_dynamic_cohorts', $a),
            $proficient && $all => get_string('condition:competency_proficiency_all_proficient', 'tool_dynamic_cohorts', $a),
            !$all => get_string('condition:competency_proficiency_any_notproficient', 'tool_dynamic_cohorts', $a),
            default => get_string('condition:competency_proficiency_all_notproficient', 'tool_dynamic_cohorts', $a),
        };
    }

    /**
     * Human readable description of the broken condition: what is missing and how to resume.
     *
     * @return string
     */
    public function get_broken_description(): string {
        if ($this->is_outside_recorded_scope()) {
            return get_string('brokenoutofscope', 'tool_dynamic_cohorts');
        }

        if ($missing = $this->get_missing_competencyids()) {
            return get_string('competencyids_broken', 'tool_dynamic_cohorts', implode(', ', $missing));
        }

        if ($this->is_course_missing()) {
            return get_string('missingcourse', 'tool_dynamic_cohorts');
        }

        return parent::get_broken_description();
    }

    /**
     * Is condition broken.
     *
     * An unconfigured instance is broken while competencies are disabled, so the condition is not
     * offered then; a configured one keeps evaluating the stored ratings.
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

        if (empty($this->get_competencyids()) || !empty($this->get_missing_competencyids())) {
            return true;
        }

        return $this->is_course_missing();
    }

    /**
     * Whether the one-course source names a course that no longer exists.
     *
     * @return bool
     */
    protected function is_course_missing(): bool {
        global $DB;

        if ($this->get_int('source', self::SOURCE_GLOBAL) !== self::SOURCE_COURSE) {
            return false;
        }
        return !$DB->record_exists('course', ['id' => $this->get_int('courseid', 0)]);
    }

    /**
     * SQL for the configured condition.
     *
     * "Any" is an EXISTS, "all" a correlated count, both in WHERE with no join: a grouped derived
     * table is materialised whole by MariaDB for every one-user evaluation when it is negated or
     * under OR (docs/competency-conditions/PLAN.md, "S0: both database families"). COUNT(DISTINCT)
     * because the any-course source holds one row per course.
     *
     * @return condition_sql
     */
    public function get_sql(): condition_sql {
        global $DB;

        if ($this->is_broken()) {
            return new condition_sql('', '1=0', []);
        }

        $ids = $this->get_competencyids();
        $alias = condition_sql::generate_table_alias();
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, condition_sql::generate_param_alias());

        $source = $this->get_int('source', self::SOURCE_GLOBAL);
        $table = $source === self::SOURCE_GLOBAL ? 'competency_usercomp' : 'competency_usercompcourse';
        $filter = "{$alias}.userid = u.id AND {$alias}.competencyid {$insql} AND {$alias}.proficiency = 1";
        if ($source === self::SOURCE_COURSE) {
            $courseparam = condition_sql::generate_param_alias();
            $filter .= " AND {$alias}.courseid = :{$courseparam}";
            $params[$courseparam] = $this->get_int('courseid', 0);
        }

        $all = $this->get_int('match', self::MATCH_ANY) === self::MATCH_ALL;
        $proficient = $this->get_int('state', self::STATE_PROFICIENT) === self::STATE_PROFICIENT;

        if ($all === $proficient) {
            // Proficient in all, or not proficient in any (lacking at least one): count the proficient ones.
            $countparam = condition_sql::generate_param_alias();
            $params[$countparam] = count($ids);
            $operator = $proficient ? '=' : '<';
            $where = "(SELECT COUNT(DISTINCT {$alias}.competencyid) FROM {{$table}} {$alias} WHERE {$filter})"
                . " {$operator} :{$countparam}";
        } else {
            // Proficient in at least one, or not proficient in every one: is there a proficient one.
            $where = ($proficient ? '' : 'NOT ') . "EXISTS (SELECT 1 FROM {{$table}} {$alias} WHERE {$filter})";
        }

        return new condition_sql('', $where, $params);
    }

    /**
     * A rating is written with competency_evidence_created, in a course and site-wide alike.
     *
     * @return string[]
     */
    public function get_events(): array {
        return [
            '\core\event\competency_evidence_created',
        ];
    }

    /**
     * Relevant when the evidence can change a proficiency and concerns a configured competency.
     *
     * Only the complete and override actions set a rating; a log action leaves it as it was.
     *
     * @param \core\event\base $event The event.
     * @return bool
     */
    public function is_relevant_event(\core\event\base $event): bool {
        $action = (int) ($event->other['action'] ?? \core_competency\evidence::ACTION_OVERRIDE);
        if ($action === \core_competency\evidence::ACTION_LOG) {
            return false;
        }
        return in_array((int) ($event->other['competencyid'] ?? 0), $this->get_competencyids(), true);
    }
}

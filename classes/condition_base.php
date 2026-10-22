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

/**
 * Abstract class that all conditions must extend.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class condition_base {
    /**
     * Value for text field types.
     */
    public const FIELD_DATA_TYPE_TEXT = 'text';

    /**
     * Value for menu field types.
     */
    public const FIELD_DATA_TYPE_MENU = 'menu';

    /**
     * Value for select field types.
     */
    public const FIELD_DATA_TYPE_SELECT = 'select';

    /**
     * Value for checkbox field types.
     */
    public const FIELD_DATA_TYPE_CHECKBOX = 'checkbox';

    /**
     * Value for date field types.
     */
    public const FIELD_DATA_TYPE_DATE = 'date';

    /**
     * Value for date field types.
     */
    public const FIELD_DATA_TYPE_DATETIME = 'datetime';

    /**
     * Value for autocomplete field types.
     */
    public const FIELD_DATA_TYPE_AUTOCOMPLETE = 'autocomplete';

    /**
     * Value for operator text contains.
     */
    public const TEXT_CONTAINS = 1;

    /**
     * Value for operator text doesn't contain.
     */
    public const TEXT_DOES_NOT_CONTAIN = 2;

    /**
     * Value for operator text is equal to.
     */
    public const TEXT_IS_EQUAL_TO = 3;

    /**
     * Value for operator text starts with.
     */
    public const TEXT_STARTS_WITH = 4;

    /**
     * Value for operator text ends with.
     */
    public const TEXT_ENDS_WITH = 5;

    /**
     * Value for operator text is empty.
     */
    public const TEXT_IS_EMPTY = 6;

    /**
     * Value for operator text is not empty.
     */
    public const TEXT_IS_NOT_EMPTY = 7;

    /**
     * Value for operator text is not equal to.
     */
    public const TEXT_IS_NOT_EQUAL_TO = 8;

    /**
     * Value for operator date is after.
     */
    public const DATE_IS_AFTER = 1;

    /**
     * Value for operator date is before.
     */
    public const DATE_IS_BEFORE = 2;

    /**
     * Value for operator date is in the past.
     */
    public const DATE_IN_THE_PAST = 30;

    /**
     * Value for operator date is in the future.
     */
    public const DATE_IN_THE_FUTURE = 40;

    /**
     * Condition persistent object.
     *
     * @var condition $condition
     */
    protected $condition;

    /**
     * The course category context the condition is written for, or null when unscoped.
     *
     * Set by whoever builds or validates the condition for a category-level user
     * ({@see \tool_dynamic_cohorts\local\rule_access::condition_scope()}); never set while a rule is
     * processed, so get_sql() cannot depend on it (a condition that needs its scope at that
     * point records it in its config, {@see self::record_scope()}).
     *
     * @var \core\context|null $scope
     */
    protected $scope = null;

    /**
     * Protected constructor.
     */
    protected function __construct() {
    }

    /**
     * A copy gets its own persistent, so setting its config or scope leaves the original alone.
     */
    public function __clone() {
        if ($this->condition) {
            $this->condition = clone $this->condition;
        }
    }

    /**
     * Return an instance of condition object.
     *
     * @param int $id
     * @param \stdClass|null $record
     * @return \tool_dynamic_cohorts\condition_base|null
     */
    final public static function get_instance(int $id = 0, ?\stdClass $record = null): ?condition_base {
        $condition = new condition($id, $record);

        // In case we are getting the instance without underlying persistent data.
        if (!$classname = $condition->get('classname')) {
            $classname = get_called_class();
            $condition->set('classname', $classname);
        }

        if (!class_exists($classname) || !is_subclass_of($classname, self::class)) {
            return null;
        }

        $instance = new $classname();
        $instance->condition = $condition;

        return $instance;
    }

    /**
     * Gets required config data from submitted condition form data.
     *
     * @param \stdClass $formdata Form data generated via $mform->get_data()
     * @return array
     */
    public static function retrieve_config_data(\stdClass $formdata): array {
        $configdata = (array)$formdata;

        // Everything except these fields is considered as config data.
        unset($configdata['id']);
        unset($configdata['ruleid']);
        unset($configdata['sortorder']);

        return $configdata;
    }

    /**
     * A config data for that condition.
     *
     * @return array
     */
    public function get_config_data(): array {
        return json_decode($this->condition->get('configdata'), true);
    }

    /**
     * Sets config data.
     *
     * @param array $configdata
     */
    public function set_config_data(array $configdata): void {
        $this->condition->set('configdata', json_encode($configdata));
    }

    /**
     * Gets condition persistent record.
     *
     * @return condition
     */
    public function get_record(): condition {
        return $this->condition;
    }

    /**
     * Returns a rule record for the given condition.
     *
     * @return rule|null
     */
    public function get_rule(): ?rule {
        return rule::get_record(['id' => $this->get_record()->get('ruleid')]) ?: null;
    }

    /**
     * Whether this condition can be offered to a user who manages rules in a course category.
     *
     * False by default, so a condition from another plugin stays unavailable there until it
     * scopes its referents and an administrator enables it (setting categoryconditions).
     *
     * @return bool
     */
    public function supports_category_scope(): bool {
        return false;
    }

    /**
     * Set the course category context the condition is written for; null for none.
     *
     * @param \core\context|null $scope Course category context, or null when unscoped.
     */
    public function set_scope(?\core\context $scope): void {
        $this->scope = $scope;
    }

    /**
     * Errors for referents of the configured condition that lie outside the scope.
     *
     * Called by the condition modal and again when the rule is saved, so the option lists the
     * modal offers and the server-side check cannot disagree. Without a scope there is nothing
     * to check.
     *
     * @return array Form errors keyed by element name.
     */
    public function validate_scope(): array {
        return [];
    }

    /**
     * Store the scope in the config data, for a condition that needs it while the rule is processed.
     *
     * Called on save for every new or changed condition of a scoped user, before validate_scope().
     */
    public function record_scope(): void {
    }

    /**
     * Whether a context lies inside the scope (the scope itself or below it); always true unscoped.
     *
     * @param \core\context $context Context to test.
     * @return bool
     */
    protected function in_scope(\core\context $context): bool {
        if ($this->scope === null) {
            return true;
        }
        return $context->id == $this->scope->id || str_starts_with($context->path, $this->scope->path . '/');
    }

    /**
     * Whether a course lies inside the scope; a course that does not exist does not.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    protected function course_in_scope(int $courseid): bool {
        $context = \core\context\course::instance($courseid, IGNORE_MISSING);
        return $context ? $this->in_scope($context) : $this->scope === null;
    }

    /**
     * Add a course selector: core's searchable course element, or the scope's courses when scoped.
     *
     * Core's element searches every course the user may see, so a category-level user gets a
     * list of the courses under the scope instead.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param string $name Element name.
     * @param string $label Element label.
     * @param array $attributes Course element attributes; only onlywithcompletion is honoured when scoped.
     */
    protected function add_course_element(\MoodleQuickForm $mform, string $name, string $label, array $attributes = []): void {
        global $DB;

        if ($this->scope === null) {
            $mform->addElement('course', $name, $label, $attributes);
            return;
        }

        $sql = "SELECT c.id, c.fullname
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :courselevel
                 WHERE " . $DB->sql_like('ctx.path', ':path');
        $params = ['courselevel' => CONTEXT_COURSE, 'path' => $DB->sql_like_escape($this->scope->path) . '/%'];
        if (!empty($attributes['onlywithcompletion'])) {
            $sql .= ' AND c.enablecompletion = 1';
        }

        $options = ['' => get_string('choosedots')];
        foreach ($DB->get_records_sql($sql . ' ORDER BY c.fullname', $params) as $course) {
            $coursecontext = \core\context\course::instance($course->id);
            $options[$course->id] = format_string($course->fullname, true, ['context' => $coursecontext]);
        }
        $mform->addElement('autocomplete', $name, $label, $options);
    }

    /**
     * SQL restricting a cohort table to the cohorts usable from a scope.
     *
     * The cohorts of the scope and of its subcategories, whatever their visibility, plus the
     * visible cohorts of its parent contexts, which is how core offers cohorts below a context
     * (cohort_get_available_cohorts()). Read from the tables alone, not from the current user, so
     * it means the same thing in a rule's processing as in its form.
     *
     * @param string $cohortalias Alias of the {cohort} table.
     * @param \core\context $scope Course category context.
     * @return array [string $sql, array $params]
     */
    public static function scoped_cohorts_sql(string $cohortalias, \core\context $scope): array {
        global $DB;

        $ctxalias = condition_sql::generate_table_alias();
        $idparam = condition_sql::generate_param_alias();
        $pathparam = condition_sql::generate_param_alias();
        $params = [$idparam => $scope->id, $pathparam => $DB->sql_like_escape($scope->path) . '/%'];

        $parentsql = '';
        if ($parents = $scope->get_parent_context_ids()) {
            [$insql, $inparams] = $DB->get_in_or_equal($parents, SQL_PARAMS_NAMED, condition_sql::generate_param_alias());
            $parentsql = " OR ({$cohortalias}.contextid {$insql} AND {$cohortalias}.visible = 1)";
            $params += $inparams;
        }

        $sql = "({$cohortalias}.contextid IN (SELECT {$ctxalias}.id
                                                FROM {context} {$ctxalias}
                                               WHERE {$ctxalias}.id = :{$idparam}
                                                  OR " . $DB->sql_like("{$ctxalias}.path", ":{$pathparam}") . ")
                 {$parentsql})";

        return [$sql, $params];
    }

    /**
     * The cohorts usable from the scope, keyed by id: {@see self::scoped_cohorts_sql()}.
     *
     * @return array Cohort records.
     */
    protected function get_scoped_cohorts(): array {
        global $DB;

        [$sql, $params] = self::scoped_cohorts_sql('c', $this->scope);
        return $DB->get_records_sql("SELECT c.* FROM {cohort} c WHERE {$sql} ORDER BY c.name, c.idnumber", $params);
    }

    /**
     * Gets a list of event classes the condition will be triggered on.
     *
     * @return array
     */
    public function get_events(): array {
        return [];
    }

    /**
     * Human readable description of the broken condition.
     *
     * @return string
     */
    public function get_broken_description(): string {
        return $this->get_record()->get('configdata');
    }

    /**
     * Returns the name of the condition
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Add condition config form elements.
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    abstract public function config_form_add(\MoodleQuickForm $mform): void;

    /**
     * Validates conditions form elements.
     *
     * @param array $data Form data.
     * @return array Errors array.
     */
    abstract public function config_form_validate(array $data): array;

    /**
     * Returns elements to extend SQL for filtering users.
     * @return condition_sql
     */
    abstract public function get_sql(): condition_sql;

    /**
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    abstract public function get_config_description(): string;

    /**
     * Indicate that condition is broken.
     *
     * @return bool
     */
    abstract public function is_broken(): bool;
}

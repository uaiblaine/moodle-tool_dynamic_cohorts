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

namespace tool_dynamic_cohorts\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_exception;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_manager;
use tool_dynamic_cohorts\local\rule_access;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\competency_proficiency;

/**
 * Competency search for the competency proficiency condition's selector.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_competencies extends external_api {
    /** Results returned at most; the selector asks the user to narrow the search beyond that. */
    public const MAX_RESULTS = 50;

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Text in the competency or framework short name, or the ID number'),
            'contextid' => new external_value(
                PARAM_INT,
                'System or course category context the rule is edited from; 0 for the system context',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Competencies matching the query, among those of the frameworks usable where the rule is edited.
     *
     * Read from the tables, not through \core_competency\api, whose listing follows the viewer's
     * competency capabilities rather than the rule manager's.
     *
     * @param string $query Search text.
     * @param int $contextid System or course category context the rule is edited from, 0 for the system.
     * @return array
     * @throws moodle_exception When the condition is not available to a category-level user.
     */
    public static function execute(string $query, int $contextid = 0): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['query' => $query, 'contextid' => $contextid]);

        $context = rule_access::context_from_id($params['contextid']);
        self::validate_context($context);
        rule_access::require_manage($context);

        $sqlparams = [];
        $where = [];

        // The same gate as the condition form: a category-level user sees only what the category offers.
        $scope = rule_access::condition_scope($context);
        if ($scope !== null) {
            if (!in_array(competency_proficiency::class, condition_manager::category_conditions(), true)) {
                $name = get_string('condition:competency_proficiency', 'tool_dynamic_cohorts');
                throw new moodle_exception('conditionnotincategory', 'tool_dynamic_cohorts', '', $name);
            }
            [$scopesql, $scopeparams] = condition_base::scoped_contexts_sql('f.contextid', 'f.visible', $scope);
            $where[] = $scopesql;
            $sqlparams += $scopeparams;
        }

        $text = trim($params['query']);
        if ($text !== '') {
            $like = [];
            foreach (['c.shortname', 'c.idnumber', 'f.shortname'] as $i => $column) {
                $like[] = $DB->sql_like($column, ":q{$i}", false, false);
                $sqlparams["q{$i}"] = '%' . $DB->sql_like_escape($text) . '%';
            }
            $where[] = '(' . implode(' OR ', $like) . ')';
        }

        $sql = "SELECT c.id, c.shortname, c.idnumber, f.shortname AS frameworkname, f.contextid
                  FROM {competency} c
                  JOIN {competency_framework} f ON f.id = c.competencyframeworkid"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY f.shortname, c.shortname, c.id';

        $result = [];
        foreach ($DB->get_records_sql($sql, $sqlparams, 0, self::MAX_RESULTS) as $record) {
            $result[] = ['id' => (int) $record->id, 'label' => competency_proficiency::format_label($record)];
        }

        return $result;
    }

    /**
     * Describes the result.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Competency id'),
            // Escaped HTML: core's autocomplete inserts a label as HTML.
            'label' => new external_value(PARAM_RAW, 'Framework and competency, escaped for HTML'),
        ]));
    }
}

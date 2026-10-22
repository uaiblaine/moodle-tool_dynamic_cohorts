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

/**
 * Data generator for tool_dynamic_cohorts.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dynamic_cohorts_generator extends component_generator_base {
    /**
     * Create a rule pointing at a cohort, disabled and without conditions.
     *
     * @param array $record Fields: name, cohortid; optional description, enabled.
     * @return \tool_dynamic_cohorts\rule
     */
    public function create_rule(array $record): \tool_dynamic_cohorts\rule {
        $rule = new \tool_dynamic_cohorts\rule(0, (object) [
            'name' => $record['name'],
            'cohortid' => (int) $record['cohortid'],
            'description' => $record['description'] ?? '',
            'enabled' => (int) ($record['enabled'] ?? 0),
        ]);
        $rule->create();

        return $rule;
    }
}

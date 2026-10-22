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
 * Behat data generator for tool_dynamic_cohorts.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_dynamic_cohorts_generator extends behat_generator_base {
    /**
     * Entities this plugin's steps can create: "the following "tool_dynamic_cohorts > rules" exist".
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'rules' => [
                'singular' => 'rule',
                'datagenerator' => 'rule',
                'required' => ['name', 'cohort'],
                'switchids' => ['cohort' => 'cohortid'],
            ],
        ];
    }
}

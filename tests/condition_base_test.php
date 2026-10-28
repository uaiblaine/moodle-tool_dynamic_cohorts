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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the scope helpers of condition_base.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(condition_base::class)]
final class condition_base_test extends \advanced_testcase {
    /**
     * The scope and its subcategories whatever the visibility, and only the visible rows of parent contexts.
     */
    public function test_scoped_contexts_sql_on_competency_frameworks(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');

        $a = $this->getDataGenerator()->create_category();
        $a1 = $this->getDataGenerator()->create_category(['parent' => $a->id]);
        $b = $this->getDataGenerator()->create_category();
        $ctxa = \core\context\coursecat::instance($a->id);
        $ctxa1 = \core\context\coursecat::instance($a1->id);
        $ctxb = \core\context\coursecat::instance($b->id);
        $system = \core\context\system::instance();

        $frameworks = [
            'systemvisible' => $generator->create_framework(['contextid' => $system->id, 'visible' => 1]),
            'systemhidden' => $generator->create_framework(['contextid' => $system->id, 'visible' => 0]),
            'ahidden' => $generator->create_framework(['contextid' => $ctxa->id, 'visible' => 0]),
            'a1hidden' => $generator->create_framework(['contextid' => $ctxa1->id, 'visible' => 0]),
            'bvisible' => $generator->create_framework(['contextid' => $ctxb->id, 'visible' => 1]),
        ];
        $names = array_flip(array_map(fn($framework) => (int) $framework->get('id'), $frameworks));

        $usable = function (\core\context $scope) use ($DB, $names): array {
            [$sql, $params] = condition_base::scoped_contexts_sql('f.contextid', 'f.visible', $scope);
            $ids = $DB->get_fieldset_sql("SELECT f.id FROM {competency_framework} f WHERE {$sql}", $params);
            $result = array_map(fn($id) => $names[(int) $id], $ids);
            sort($result);
            return $result;
        };

        // Category A: its own hidden framework and its subcategory's, the visible system one.
        $this->assertSame(['a1hidden', 'ahidden', 'systemvisible'], $usable($ctxa));
        // Subcategory A1: A is a parent now, so A's hidden framework drops out.
        $this->assertSame(['a1hidden', 'systemvisible'], $usable($ctxa1));
        // Sibling B: nothing of A's subtree.
        $this->assertSame(['bvisible', 'systemvisible'], $usable($ctxb));
    }
}

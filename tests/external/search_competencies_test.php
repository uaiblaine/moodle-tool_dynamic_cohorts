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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the competency search web service.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(search_competencies::class)]
final class search_competencies_test extends \advanced_testcase {
    /**
     * Call the web service as the current user, as the browser does.
     *
     * @param string $query Search text.
     * @param int $contextid Context id, 0 for the system.
     * @return array The response.
     */
    private function call(string $query, int $contextid = 0): array {
        $_POST['sesskey'] = sesskey();
        return \core_external\external_api::call_external_function(
            'tool_dynamic_cohorts_search_competencies',
            ['query' => $query, 'contextid' => $contextid],
            true
        );
    }

    /**
     * Search by competency, ID number and framework, with labels escaped for the autocomplete.
     */
    public function test_search(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['shortname' => 'Skills & co']);
        $other = $generator->create_framework(['shortname' => 'Languages']);
        $writing = $generator->create_competency(['competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Writing', 'idnumber' => 'W-1 <b>"x"']);
        $generator->create_competency(['competencyframeworkid' => $other->get('id'), 'shortname' => 'French',
            'idnumber' => 'FR']);

        $response = $this->call('writ');
        $this->assertFalse($response['error']);
        // The ID number is raw text in core: it arrives escaped like the names, once.
        $expected = [['id' => (int) $writing->get('id'), 'label' => 'Skills &amp; co › Writing (W-1 &lt;b&gt;&quot;x&quot;)']];
        $this->assertSame($expected, $response['data']);

        $this->assertSame([(int) $writing->get('id')], array_column($this->call('w-1')['data'], 'id'));
        $this->assertCount(1, $this->call('languages')['data']);
        $this->assertCount(2, $this->call('')['data']);
    }

    /**
     * Results stop at the cap.
     */
    public function test_result_cap(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        for ($i = 0; $i <= search_competencies::MAX_RESULTS; $i++) {
            $generator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        }

        $this->assertCount(search_competencies::MAX_RESULTS, $this->call('')['data']);
    }

    /**
     * A user without the plugin's capabilities is refused. A category manager is served the
     * competencies of the frameworks usable from their category, and refused once the condition
     * is switched off for categories.
     */
    public function test_access(): void {
        $this->resetAfterTest();

        $this->setUser($this->getDataGenerator()->create_user());
        $response = $this->call('');
        $this->assertTrue($response['error']);
        $this->assertSame('nopermissions', $response['exception']->errorcode);

        $category = $this->getDataGenerator()->create_category();
        $sibling = $this->getDataGenerator()->create_category();
        $context = \core\context\coursecat::instance($category->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $frameworks = [
            'own hidden' => ['contextid' => $context->id, 'visible' => 0],
            'sibling' => ['contextid' => \core\context\coursecat::instance($sibling->id)->id, 'visible' => 1],
            'system visible' => ['contextid' => \core\context\system::instance()->id, 'visible' => 1],
            'system hidden' => ['contextid' => \core\context\system::instance()->id, 'visible' => 0],
        ];
        $ids = [];
        foreach ($frameworks as $name => $record) {
            $framework = $generator->create_framework($record + ['shortname' => $name]);
            $ids[$name] = (int) $generator->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');
        }

        $manager = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/dynamic_cohorts:manage', CAP_ALLOW, $roleid, $context->id);
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $manager->id, $context->id);
        $this->setUser($manager);

        $response = $this->call('', $context->id);
        $this->assertFalse($response['error']);
        $found = array_column($response['data'], 'id');
        sort($found);
        $expected = [$ids['own hidden'], $ids['system visible']];
        sort($expected);
        $this->assertSame($expected, $found);

        set_config('categoryconditions', '', 'tool_dynamic_cohorts');
        $response = $this->call('', $context->id);
        $this->assertTrue($response['error']);
        $this->assertSame('conditionnotincategory', $response['exception']->errorcode);

        // The control: an administrator searching from the same category is served everything.
        $this->setAdminUser();
        $this->assertCount(4, $this->call('', $context->id)['data']);
    }
}

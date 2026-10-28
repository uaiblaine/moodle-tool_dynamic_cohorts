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

namespace tool_dynamic_cohorts\local;

use core_reportbuilder\external\systemreports\retrieve;
use core_reportbuilder\system_report_factory;
use PHPUnit\Framework\Attributes\CoversClass;
use tool_dynamic_cohorts\reportbuilder\local\systemreports\rules;
use tool_dynamic_cohorts\rule;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/dynamic_cohorts/lib.php');

/**
 * The rules page in a course category: access, listed rules, navigation and row actions.
 *
 * A category manager holds tool/dynamic_cohorts:manage and moodle/cohort:assign in category A
 * only. A1 is a subcategory of A, A2 a subcategory of A where the plugin capability is
 * prohibited, B a sibling category; one more rule points at a cohort that no longer exists.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(rules_page::class)]
#[CoversClass(rules::class)]
final class rules_page_test extends \advanced_testcase {
    /** @var array Contexts by key: a, a1, a2, b, system. */
    private array $contexts = [];

    /** @var array Rules by the key of their cohort's context, plus 'orphan'. */
    private array $rules = [];

    /** @var int The category manager. */
    private int $manager;

    /** @var int Holds the plugin capability in A but not moodle/cohort:assign. */
    private int $noassign;

    /**
     * Build the categories, cohorts, rules and users.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $a = $generator->create_category();
        $this->contexts = [
            'a' => \core\context\coursecat::instance($a->id),
            'a1' => \core\context\coursecat::instance($generator->create_category(['parent' => $a->id])->id),
            'a2' => \core\context\coursecat::instance($generator->create_category(['parent' => $a->id])->id),
            'b' => \core\context\coursecat::instance($generator->create_category()->id),
            'system' => \core\context\system::instance(),
        ];

        foreach ($this->contexts as $key => $context) {
            $cohort = $generator->create_cohort(['contextid' => $context->id]);
            $rule = new rule(0, (object) ['name' => 'Rule ' . $key, 'cohortid' => $cohort->id]);
            $rule->save();
            $this->rules[$key] = $rule;
        }
        $orphan = new rule(0, (object) ['name' => 'Rule orphan', 'cohortid' => 999999]);
        $orphan->save();
        $this->rules['orphan'] = $orphan;

        $roleid = $generator->create_role();
        assign_capability('tool/dynamic_cohorts:manage', CAP_ALLOW, $roleid, $this->contexts['a']->id);
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $this->contexts['a']->id);
        assign_capability('tool/dynamic_cohorts:manage', CAP_PROHIBIT, $roleid, $this->contexts['a2']->id);
        $this->manager = (int) $generator->create_user()->id;
        role_assign($roleid, $this->manager, $this->contexts['a']->id);

        $noassignroleid = $generator->create_role();
        assign_capability('tool/dynamic_cohorts:manage', CAP_ALLOW, $noassignroleid, $this->contexts['a']->id);
        $this->noassign = (int) $generator->create_user()->id;
        role_assign($noassignroleid, $this->noassign, $this->contexts['a']->id);
    }

    /**
     * Ids of a list of rules, sorted.
     *
     * @param array $rules Rule persistents.
     * @return array
     */
    private function ids(array $rules): array {
        $ids = array_map(fn(rule $rule) => (int) $rule->get('id'), $rules);
        sort($ids);
        return $ids;
    }

    /**
     * Ids of the rules by key, sorted.
     *
     * @param array $keys Keys of $this->rules.
     * @return array
     */
    private function rule_ids(array $keys): array {
        return $this->ids(array_map(fn(string $key) => $this->rules[$key], $keys));
    }

    /**
     * Set the page up the way index.php does, on a fresh $PAGE.
     *
     * @param int $contextid Context id from the request.
     * @return \core\context
     */
    private function setup_page(int $contextid): \core\context {
        global $PAGE;

        $PAGE = new \moodle_page();
        return rules_page::setup($contextid);
    }

    /**
     * The page URL carries the category, not the system context.
     */
    public function test_url(): void {
        $systemurl = rules_page::url($this->contexts['system']);
        $this->assertSame('/admin/tool/dynamic_cohorts/index.php', $systemurl->out_as_local_url(false));
        $this->assertEquals($this->contexts['a']->id, rules_page::url($this->contexts['a'])->get_param('contextid'));
    }

    /**
     * The page opens only where its user may manage rules.
     */
    public function test_setup_access(): void {
        $this->setUser($this->manager);

        foreach (['b', 'a2'] as $key) {
            try {
                $this->setup_page($this->contexts[$key]->id);
                $this->fail('The page opened in ' . $key . '.');
            } catch (\required_capability_exception $exception) {
                $this->assertInstanceOf(\required_capability_exception::class, $exception);
            }
        }

        // The system page is the site administration page, which core refuses itself.
        try {
            $this->setup_page(0);
            $this->fail('The system page opened.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('accessdenied', $exception->errorcode);
        }

        $course = $this->getDataGenerator()->create_course(['category' => $this->contexts['a']->instanceid]);
        try {
            $this->setup_page(\core\context\course::instance($course->id)->id);
            $this->fail('A course context was accepted.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidcontext', $exception->errorcode);
        }

        // The controls: A and its subcategory A1.
        $this->assertEquals($this->contexts['a']->id, $this->setup_page($this->contexts['a']->id)->id);
        $this->assertEquals($this->contexts['a1']->id, $this->setup_page($this->contexts['a1']->id)->id);

        // Not without moodle/cohort:assign.
        $this->setUser($this->noassign);
        $this->expectException(\required_capability_exception::class);
        $this->setup_page($this->contexts['a']->id);
    }

    /**
     * The rules a page lists, for its warnings: the category's subtree, or everything at the system level.
     */
    public function test_rules_in(): void {
        $this->assertEquals($this->rule_ids(['a', 'a1', 'a2']), $this->ids(rules_page::rules_in($this->contexts['a'])));
        $this->assertEquals($this->rule_ids(['a1']), $this->ids(rules_page::rules_in($this->contexts['a1'])));
        $this->assertEquals($this->rule_ids(['b']), $this->ids(rules_page::rules_in($this->contexts['b'])));
        $this->assertEquals(
            $this->rule_ids(['a', 'a1', 'a2', 'b', 'system', 'orphan']),
            $this->ids(rules_page::rules_in($this->contexts['system']))
        );
    }

    /**
     * The category's settings navigation offers the page to those who may use it.
     */
    public function test_category_navigation(): void {
        $add = function (string $key): ?\navigation_node {
            $node = \navigation_node::create('Category');
            tool_dynamic_cohorts_extend_navigation_category_settings($node, $this->contexts[$key]);
            return $node->find(rules_page::NAVIGATION_KEY, \navigation_node::TYPE_SETTING) ?: null;
        };

        $this->setUser($this->manager);
        $this->assertNull($add('b'));
        $this->assertNull($add('a2'));
        $node = $add('a');
        $this->assertNotNull($node);
        $this->assertEquals($this->contexts['a']->id, $node->action->get_param('contextid'));

        $this->setUser($this->noassign);
        $this->assertNull($add('a'));
    }

    /**
     * Rule names the rules report lists in a context, sorted.
     *
     * @param \core\context $context The page's context.
     * @return array
     */
    private function listed(\core\context $context): array {
        $result = retrieve::execute(rules::class, ['contextid' => $context->id], 'tool_dynamic_cohorts', '', 0, [], 0, 100);
        $names = array_map(fn(array $row) => strip_tags($row['columns'][0]), $result['data']['rows']);
        sort($names);
        return $names;
    }

    /**
     * The rules report lists the category's subtree, and the orphan rule only at the system level.
     */
    public function test_report_rows(): void {
        $this->setUser($this->manager);
        $this->assertSame(['Rule a', 'Rule a1', 'Rule a2'], $this->listed($this->contexts['a']));
        $this->assertSame(['Rule a1'], $this->listed($this->contexts['a1']));

        // The client picks the report's context on core's retrieve service: only the page's levels,
        // and only where the user may manage rules. A course in A inherits both capabilities.
        // Enrolled, so core's own validate_context() lets the course through and can_view() decides.
        $course = $this->getDataGenerator()->create_course(['category' => $this->contexts['a']->instanceid]);
        $this->getDataGenerator()->enrol_user($this->manager, $course->id);
        foreach ([$this->contexts['system'], $this->contexts['b'], \core\context\course::instance($course->id)] as $context) {
            try {
                $this->listed($context);
                $this->fail('The rules report opened in context ' . $context->id . '.');
            } catch (\core_reportbuilder\exception\report_access_exception $exception) {
                $this->assertInstanceOf(\core_reportbuilder\exception\report_access_exception::class, $exception);
            }
        }

        $this->setAdminUser();
        $this->assertSame(['Rule b'], $this->listed($this->contexts['b']));
        $this->assertSame(
            ['Rule a', 'Rule a1', 'Rule a2', 'Rule b', 'Rule orphan', 'Rule system'],
            $this->listed($this->contexts['system'])
        );
    }

    /**
     * The context column names where each rule was written; its filter is offered at the system level only.
     */
    public function test_report_context_column(): void {
        $this->setAdminUser();
        $parameters = ['contextid' => $this->contexts['system']->id];
        $result = retrieve::execute(rules::class, $parameters, 'tool_dynamic_cohorts', '', 0, [], 0, 100);

        // Columns: rule name, description, cohort, context.
        $contexts = [];
        foreach ($result['data']['rows'] as $row) {
            $contexts[strip_tags($row['columns'][0])] = $row['columns'][3];
        }
        $this->assertSame(get_string('coresystem'), $contexts['Rule system']);
        $this->assertSame($this->contexts['a1']->get_context_name(false), $contexts['Rule a1']);
        $this->assertSame($this->contexts['b']->get_context_name(false), $contexts['Rule b']);
        // A rule whose cohort is gone has no context to name.
        $this->assertSame('', $contexts['Rule orphan']);

        $system = system_report_factory::create(rules::class, $this->contexts['system'], 'tool_dynamic_cohorts');
        $this->assertArrayHasKey('cohort:context', $system->get_filters());

        // Not on a category page: its options would name categories outside the manager's subtree.
        $this->setUser($this->manager);
        $category = system_report_factory::create(rules::class, $this->contexts['a'], 'tool_dynamic_cohorts');
        $this->assertArrayNotHasKey('cohort:context', $category->get_filters());
        $this->assertNotNull($category->get_column('cohort:context'));
    }

    /**
     * Row actions are decided in each rule's own context, not the page's.
     */
    public function test_report_row_actions(): void {
        global $PAGE;

        $this->setUser($this->manager);
        $PAGE->set_url(rules_page::url($this->contexts['a']));
        $html = system_report_factory::create(rules::class, $this->contexts['a'], 'tool_dynamic_cohorts')->output();

        $hasedit = function (string $key) use ($html): bool {
            $id = (int) $this->rules[$key]->get('id');
            return (bool) preg_match('/<a\b[^>]*tool-dynamic-cohorts-rule-edit[^>]*data-ruleid="' . $id . '"/', $html);
        };

        $this->assertTrue($hasedit('a'));
        $this->assertTrue($hasedit('a1'));
        // Listed, because it is in A's subtree, but not manageable: the capability is prohibited there.
        $this->assertStringContainsString('Rule a2', $html);
        $this->assertFalse($hasedit('a2'));
    }
}

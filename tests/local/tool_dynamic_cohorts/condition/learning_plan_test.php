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

use core_competency\api;
use core_competency\plan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\rule;

/**
 * Tests of the learning plan condition.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(learning_plan::class)]
final class learning_plan_test extends \advanced_testcase {
    /** @var \core_competency\template[] Templates by key. */
    private $templates = [];

    /** @var \stdClass[] Test users by key. */
    private $users = [];

    /**
     * Three templates.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        foreach (['t1', 't2', 't3'] as $key) {
            $this->templates[$key] = $generator->create_template(['shortname' => "Template {$key} & co"]);
        }
    }

    /**
     * Id of a template.
     *
     * @param string $key Template key.
     * @return int
     */
    private function tid(string $key): int {
        return (int) $this->templates[$key]->get('id');
    }

    /**
     * Build a condition with the given config, attached to a rule when one is given.
     *
     * @param array $configdata Config data.
     * @param rule|null $rule Rule to attach the condition to.
     * @return learning_plan
     */
    private function condition(array $configdata, ?rule $rule = null): learning_plan {
        $condition = condition_base::get_instance(0, (object) ['classname' => learning_plan::class]);
        $condition->set_config_data($configdata);
        if ($rule) {
            $record = $condition->get_record();
            $record->set('ruleid', $rule->get('id'));
            $record->set('sortorder', 0);
            $record->save();
        }
        return $condition;
    }

    /**
     * Config for the given templates, with defaults for the rest.
     *
     * @param string[] $keys Template keys.
     * @param array $extra Other config values.
     * @return array
     */
    private function config(array $keys, array $extra = []): array {
        return $extra + [
            'operator' => learning_plan::OPERATOR_HAS,
            'templateids' => array_map(fn($key) => $this->tid($key), $keys),
            'includeunlinked' => 0,
            'statuses' => [],
        ];
    }

    /**
     * A rule on a new cohort.
     *
     * @param int $realtime Whether the rule is processed in real time.
     * @return rule
     */
    private function create_rule(int $realtime = 0): rule {
        $cohort = $this->getDataGenerator()->create_cohort();
        $rule = new rule(0, (object) ['name' => 'Rule', 'enabled' => 1, 'realtime' => $realtime, 'cohortid' => $cohort->id]);
        $rule->save();
        return $rule;
    }

    /**
     * Plans of every kind: by template and status, unlinked, individual, none.
     */
    private function create_plans(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $plans = [
            't1active' => ['templateid' => $this->tid('t1'), 'status' => plan::STATUS_ACTIVE],
            't1draft' => ['templateid' => $this->tid('t1'), 'status' => plan::STATUS_DRAFT],
            't2complete' => ['templateid' => $this->tid('t2'), 'status' => plan::STATUS_COMPLETE],
            'unlinked' => ['origtemplateid' => $this->tid('t1'), 'status' => plan::STATUS_ACTIVE],
            'individual' => ['status' => plan::STATUS_ACTIVE],
            'none' => null,
        ];
        foreach ($plans as $key => $plan) {
            $this->users[$key] = $this->getDataGenerator()->create_user();
            if ($plan !== null) {
                $generator->create_plan(['userid' => $this->users[$key]->id] + $plan);
            }
        }
    }

    /**
     * Keys of the test users the condition matches, through its SQL run on the database.
     *
     * @param learning_plan $condition The condition.
     * @return string[] Sorted.
     */
    private function matching(learning_plan $condition): array {
        global $DB;

        $sql = $condition->get_sql();
        $ids = array_map(fn($user) => (int) $user->id, $this->users);
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'testuser');
        $matched = $DB->get_fieldset_sql(
            "SELECT u.id FROM {user} u {$sql->get_join()} WHERE ({$sql->get_where()}) AND u.id {$insql}",
            $sql->get_params() + $inparams
        );

        $keys = array_keys(array_intersect($ids, array_map('intval', $matched)));
        sort($keys);
        return $keys;
    }

    /**
     * Data for the plan matches.
     *
     * @return array
     */
    public static function plans_provider(): array {
        return [
            'from t1, any status' => [['t1'], [], ['t1active', 't1draft']],
            'from t1, active' => [['t1'], ['statuses' => [plan::STATUS_ACTIVE]], ['t1active']],
            'from t1, draft (status 0)' => [['t1'], ['statuses' => [plan::STATUS_DRAFT]], ['t1draft']],
            'from t1 or unlinked from it' => [['t1'], ['includeunlinked' => 1], ['t1active', 't1draft', 'unlinked']],
            'from t1 or t2, complete' => [['t1', 't2'], ['statuses' => [plan::STATUS_COMPLETE]], ['t2complete']],
            'without a plan from t1' => [['t1'], ['operator' => learning_plan::OPERATOR_HAS_NOT],
                ['individual', 'none', 't2complete', 'unlinked']],
        ];
    }

    /**
     * Templates, statuses, unlinked plans and the negation; individual plans never count.
     *
     * @param string[] $templates Template keys.
     * @param array $extra Other config values.
     * @param string[] $expected Matching user keys.
     */
    #[DataProvider('plans_provider')]
    public function test_plans(array $templates, array $extra, array $expected): void {
        $this->create_plans();
        $this->assertSame($expected, $this->matching($this->condition($this->config($templates, $extra))));
    }

    /**
     * A deleted template breaks the condition, and the description names it.
     */
    public function test_broken(): void {
        $this->assertFalse($this->condition($this->config(['t1']))->is_broken());
        $this->assertTrue($this->condition($this->config([]))->is_broken());

        $missing = $this->condition(['templateids' => [$this->tid('t1'), 999999]] + $this->config([]));
        $this->assertTrue($missing->is_broken());
        $this->assertStringContainsString('999999', $missing->get_broken_description());
        $this->assertSame('1=0', $missing->get_sql()->get_where());
    }

    /**
     * A template whose cohort sync fills the rule's own cohort breaks the condition and says why.
     */
    public function test_self_synced_template_breaks(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rule = $this->create_rule();
        $other = $this->getDataGenerator()->create_cohort();

        // The control: a template synced from another cohort is fine.
        $generator->create_template_cohort(['templateid' => $this->tid('t1'), 'cohortid' => $other->id]);
        $this->assertFalse($this->condition($this->config(['t1', 't2']), $rule)->is_broken());

        $generator->create_template_cohort(['templateid' => $this->tid('t2'), 'cohortid' => $rule->get('cohortid')]);
        $condition = $this->condition($this->config(['t1', 't2']), $rule);
        $this->assertTrue($condition->is_broken());
        $this->assertTrue($rule->is_broken(true));
        $this->assertStringContainsString('Template t2 &amp; co', $condition->get_broken_description());
        $this->assertStringContainsString("this rule's own cohort", $condition->get_broken_description());
    }

    /**
     * is_broken() is answered once per config data: asked again it reads nothing, and new config is read afresh.
     */
    public function test_is_broken_is_memoised_per_config(): void {
        global $DB;

        // Written in a category: its templates are checked against it again on every check.
        $category = \core\context\coursecat::instance($this->getDataGenerator()->create_category()->id);
        $condition = $this->condition($this->config(['t1'], [condition_base::SCOPE_KEY => (int) $category->id]));
        $this->assertFalse($condition->is_broken());
        $before = $DB->perf_get_reads();
        $this->assertFalse($condition->is_broken());
        $this->assertSame(0, $DB->perf_get_reads() - $before);

        // The control: the same instance with new config is answered afresh.
        $condition->set_config_data(['templateids' => [999999]] + $this->config([]));
        $this->assertTrue($condition->is_broken());
    }

    /**
     * The unconfigured condition is offered only while competencies are enabled.
     */
    public function test_not_offered_while_competencies_disabled(): void {
        set_config('enabled', 1, 'core_competency');
        $this->assertFalse(learning_plan::get_instance()->is_broken());

        set_config('enabled', 0, 'core_competency');
        $this->assertTrue(learning_plan::get_instance()->is_broken());
        $this->assertFalse($this->condition($this->config(['t1']))->is_broken());
    }

    /**
     * Config data is cleaned; an empty status choice is no status, not the draft status 0.
     */
    public function test_retrieve_config_data(): void {
        $formdata = (object) [
            'id' => 1,
            'sortorder' => 0,
            'operator' => '2',
            'templateids' => ['4', '4', '6'],
            'includeunlinked' => '1',
            'statuses' => [''],
        ];
        $expected = ['operator' => 2, 'templateids' => [4, 6], 'includeunlinked' => 1, 'statuses' => []];
        $this->assertSame($expected, learning_plan::retrieve_config_data($formdata));

        $formdata->statuses = ['0', '1'];
        $this->assertSame([0, 1], learning_plan::retrieve_config_data($formdata)['statuses']);
    }

    /**
     * Form validation: templates required, existing and at most the cap; statuses known.
     */
    public function test_config_form_validate(): void {
        $condition = learning_plan::get_instance();
        $valid = $this->config(['t1']);
        $this->assertSame([], $condition->config_form_validate($valid));

        $this->assertArrayHasKey('templateids', $condition->config_form_validate(['templateids' => []] + $valid));
        $this->assertArrayHasKey('templateids', $condition->config_form_validate(['templateids' => [999999]] + $valid));
        $errors = $condition->config_form_validate(['templateids' => range(1, learning_plan::MAX_TEMPLATES + 1)] + $valid);
        $max = get_string('learningplantemplates_max', 'tool_dynamic_cohorts', learning_plan::MAX_TEMPLATES);
        $this->assertSame($max, $errors['templateids'] ?? null);
        $this->assertArrayHasKey('statuses', $condition->config_form_validate(['statuses' => [7]] + $valid));
    }

    /**
     * The description names the templates escaped once, the statuses and the unlinked option.
     */
    public function test_description(): void {
        $condition = $this->condition($this->config(['t1'], [
            'operator' => learning_plan::OPERATOR_HAS_NOT,
            'statuses' => [plan::STATUS_COMPLETE],
            'includeunlinked' => 1,
        ]));
        $description = $condition->get_config_description();

        $this->assertStringStartsWith('Users without a learning plan created from Template t1 &amp; co', $description);
        $this->assertStringNotContainsString('&amp;amp;', $description);
        $this->assertStringContainsString(get_string('planstatuscomplete', 'core_competency'), $description);
        $this->assertStringContainsString(get_string('learningplanunlinked_description', 'tool_dynamic_cohorts'), $description);
    }

    /**
     * Relevance: plan events of a configured template, linked or since unlinked.
     */
    public function test_is_relevant_event(): void {
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $ont1 = $this->condition($this->config(['t1']));
        $ont2 = $this->condition($this->config(['t2']));

        $sink = $this->redirectEvents();
        $plan = api::create_plan_from_template($this->templates['t1'], $user->id);
        api::unlink_plan_from_template($plan);
        $events = $sink->get_events();
        $sink->close();

        $created = current(array_filter($events, fn($event) => $event instanceof \core\event\competency_plan_created));
        $unlinked = current(array_filter($events, fn($event) => $event instanceof \core\event\competency_plan_unlinked));
        $this->assertTrue($ont1->is_relevant_event($created));
        $this->assertFalse($ont2->is_relevant_event($created));
        // Unlinking takes the plan out of "from t1" even when unlinked plans do not count.
        $this->assertTrue($ont1->is_relevant_event($unlinked));
        $this->assertFalse($ont2->is_relevant_event($unlinked));
    }

    /**
     * Creating and deleting a plan through core's API adds and removes its owner at once.
     */
    public function test_plan_processes_the_rule_in_real_time(): void {
        global $DB, $USER;

        // The observer is non-internal: its call waits for the commit advanced_testcase would never make.
        $this->preventResetByRollback();
        set_config('realtime', 1, 'tool_dynamic_cohorts');
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $rule = $this->create_rule(1);
        $this->condition($this->config(['t1']), $rule);
        $member = fn($userid) => $DB->record_exists('cohort_members', ['cohortid' => $rule->get('cohortid'), 'userid' => $userid]);

        $plan = api::create_plan_from_template($this->templates['t1'], $user->id);
        $this->assertTrue($member($user->id));
        // The plan owner was processed, not the administrator who created it.
        $this->assertFalse($member($USER->id));

        api::delete_plan($plan->get('id'));
        $this->assertFalse($member($user->id));
    }
}

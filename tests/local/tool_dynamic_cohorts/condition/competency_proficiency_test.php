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

use core_competency\evidence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\rule_manager;

/**
 * Tests of the competency proficiency condition.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(competency_proficiency::class)]
final class competency_proficiency_test extends \advanced_testcase {
    /** @var \core_competency\competency[] Competencies by key. */
    private $competencies = [];

    /** @var \stdClass[] Test users by key. */
    private $users = [];

    /**
     * A framework with three competencies.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['shortname' => 'Framework & co']);
        foreach (['c1', 'c2', 'c3'] as $key) {
            $this->competencies[$key] = $generator->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'shortname' => "Competency {$key}",
                'idnumber' => $key,
            ]);
        }
    }

    /**
     * Build a condition with the given config.
     *
     * @param array $configdata Config data.
     * @return competency_proficiency
     */
    private function condition(array $configdata): competency_proficiency {
        $condition = condition_base::get_instance(0, (object) ['classname' => competency_proficiency::class]);
        $condition->set_config_data($configdata);
        return $condition;
    }

    /**
     * Config for the given competencies, with defaults for the rest.
     *
     * @param string[] $keys Competency keys.
     * @param array $extra Other config values.
     * @return array
     */
    private function config(array $keys, array $extra = []): array {
        return $extra + [
            'competencyids' => array_map(fn($key) => (int) $this->competencies[$key]->get('id'), $keys),
            'match' => competency_proficiency::MATCH_ANY,
            'state' => competency_proficiency::STATE_PROFICIENT,
            'source' => competency_proficiency::SOURCE_GLOBAL,
            'courseid' => 0,
        ];
    }

    /**
     * Create test users by key.
     *
     * @param string[] $keys User keys.
     */
    private function create_users(array $keys): void {
        foreach ($keys as $key) {
            $this->users[$key] = $this->getDataGenerator()->create_user();
        }
    }

    /**
     * Keys of the test users the condition matches, through its SQL run on the database.
     *
     * @param competency_proficiency $condition The condition.
     * @return string[] Sorted.
     */
    private function matching(competency_proficiency $condition): array {
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
     * Add evidence in the system context through core's API, which writes the rating and raises the event.
     *
     * @param int $userid User.
     * @param string $key Competency key.
     * @param int $action An evidence action.
     */
    private function add_evidence(int $userid, string $key, int $action): void {
        \core_competency\api::add_evidence(
            $userid,
            $this->competencies[$key],
            \core\context\system::instance(),
            $action,
            'evidence_manualoverride',
            'core_competency'
        );
    }

    /**
     * Site-wide ratings: none, no proficiency, not proficient, proficient in c1, proficient in c1 and c2.
     */
    private function create_global_ratings(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $this->create_users(['none', 'null', 'zero', 'one', 'two']);

        $rate = function (string $user, string $competency, ?int $proficiency) use ($generator): void {
            $generator->create_user_competency([
                'userid' => $this->users[$user]->id,
                'competencyid' => $this->competencies[$competency]->get('id'),
                // Core requires a grade with a proficiency and none without.
                'grade' => $proficiency === null ? null : ($proficiency ? 2 : 1),
                'proficiency' => $proficiency,
            ]);
        };
        $rate('null', 'c1', null);
        $rate('zero', 'c1', 0);
        $rate('zero', 'c2', 0);
        $rate('one', 'c1', 1);
        $rate('one', 'c2', 0);
        $rate('two', 'c1', 1);
        $rate('two', 'c2', 1);
        // Proficient in a competency the condition does not name, which must not count.
        $rate('none', 'c3', 1);
    }

    /**
     * Data for the four combinations of match and state on the site-wide rating of c1 and c2.
     *
     * @return array
     */
    public static function state_match_provider(): array {
        return [
            'proficient in at least one' => [competency_proficiency::STATE_PROFICIENT, competency_proficiency::MATCH_ANY,
                ['one', 'two']],
            'proficient in all' => [competency_proficiency::STATE_PROFICIENT, competency_proficiency::MATCH_ALL,
                ['two']],
            'not proficient in at least one' => [competency_proficiency::STATE_NOT_PROFICIENT, competency_proficiency::MATCH_ANY,
                ['none', 'null', 'one', 'zero']],
            'not proficient in any' => [competency_proficiency::STATE_NOT_PROFICIENT, competency_proficiency::MATCH_ALL,
                ['none', 'null', 'zero']],
        ];
    }

    /**
     * Each combination of state and match on the site-wide rating.
     *
     * @param int $state State.
     * @param int $match Match.
     * @param string[] $expected Matching user keys.
     */
    #[DataProvider('state_match_provider')]
    public function test_global_rating(int $state, int $match, array $expected): void {
        $this->create_global_ratings();
        $condition = $this->condition($this->config(['c1', 'c2'], ['state' => $state, 'match' => $match]));
        $this->assertSame($expected, $this->matching($condition));
    }

    /**
     * Course ratings: one course, and any course where a competency rated in two courses counts once.
     */
    public function test_course_ratings(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        $this->create_users(['bothina', 'split', 'c1twice', 'global']);

        $rate = function (string $user, string $competency, \stdClass $course) use ($generator): void {
            $generator->create_user_competency_course([
                'userid' => $this->users[$user]->id,
                'competencyid' => $this->competencies[$competency]->get('id'),
                'courseid' => $course->id,
                'grade' => 2,
                'proficiency' => 1,
            ]);
        };
        $rate('bothina', 'c1', $coursea);
        $rate('bothina', 'c2', $coursea);
        $rate('split', 'c1', $coursea);
        $rate('split', 'c2', $courseb);
        $rate('c1twice', 'c1', $coursea);
        $rate('c1twice', 'c1', $courseb);
        // A site-wide rating does not count for the course sources.
        foreach (['c1', 'c2'] as $competency) {
            $generator->create_user_competency([
                'userid' => $this->users['global']->id,
                'competencyid' => $this->competencies[$competency]->get('id'),
                'grade' => 2,
                'proficiency' => 1,
            ]);
        }

        $all = ['match' => competency_proficiency::MATCH_ALL];
        $incoursea = $all + ['source' => competency_proficiency::SOURCE_COURSE, 'courseid' => (int) $coursea->id];
        $inanycourse = ['source' => competency_proficiency::SOURCE_ANY_COURSE];

        $this->assertSame(['bothina'], $this->matching($this->condition($this->config(['c1', 'c2'], $incoursea))));
        $allinanycourse = $all + $inanycourse;
        $this->assertSame(['bothina', 'split'], $this->matching($this->condition($this->config(['c1', 'c2'], $allinanycourse))));
        $this->assertSame(
            ['bothina', 'c1twice', 'split'],
            $this->matching($this->condition($this->config(['c1', 'c2'], $inanycourse)))
        );
        $this->assertSame(['global'], $this->matching($this->condition($this->config(['c1', 'c2'], $all))));
    }

    /**
     * Under the rule operator OR, a user matching only the other condition is still matched.
     */
    public function test_neutral_under_or(): void {
        $this->create_global_ratings();
        $cohort = $this->getDataGenerator()->create_cohort();
        $rule = new rule(0, (object) [
            'name' => 'Rule',
            'enabled' => 1,
            'cohortid' => $cohort->id,
            'operator' => rule_manager::CONDITIONS_OPERATOR_OR,
        ]);
        $rule->save();

        $username = [
            'profilefield' => 'username',
            'username_operator' => user_profile::TEXT_IS_EQUAL_TO,
            'username_value' => $this->users['none']->username,
        ];
        $conditions = [
            [competency_proficiency::class, $this->config(['c1', 'c2'], ['match' => competency_proficiency::MATCH_ALL])],
            [user_profile::class, $username],
        ];
        foreach ($conditions as $sortorder => [$classname, $configdata]) {
            $condition = condition_base::get_instance(0, (object) ['classname' => $classname]);
            $condition->set_config_data($configdata);
            $record = $condition->get_record();
            $record->set('ruleid', $rule->get('id'));
            $record->set('sortorder', $sortorder);
            $record->save();
        }

        $matched = array_map('intval', array_keys(rule_manager::get_matching_users($rule)));
        $this->assertEqualsCanonicalizing([(int) $this->users['two']->id, (int) $this->users['none']->id], $matched);

        // One user at a time, as the observer asks, gives the same answer.
        foreach ($this->users as $user) {
            $one = array_keys(rule_manager::get_matching_users($rule, (int) $user->id));
            $this->assertSame(in_array((int) $user->id, $matched, true), !empty($one));
        }
    }

    /**
     * A deleted competency or course breaks the condition, and the description says what is missing.
     */
    public function test_broken(): void {
        $this->assertFalse($this->condition($this->config(['c1']))->is_broken());

        $ids = [(int) $this->competencies['c1']->get('id'), 999999];
        $missing = $this->condition(['competencyids' => $ids] + $this->config([]));
        $this->assertTrue($missing->is_broken());
        $this->assertStringContainsString('999999', $missing->get_broken_description());
        $this->assertSame('1=0', $missing->get_sql()->get_where());

        $this->assertTrue($this->condition($this->config([]))->is_broken());
        // A source the form never offers, which would read every course's ratings unrestricted.
        $this->assertTrue($this->condition($this->config(['c1'], ['source' => 9]))->is_broken());

        $nocourse = $this->condition($this->config(['c1'], [
            'source' => competency_proficiency::SOURCE_COURSE,
            'courseid' => 999999,
        ]));
        $this->assertTrue($nocourse->is_broken());
        $this->assertSame(get_string('missingcourse', 'tool_dynamic_cohorts'), $nocourse->get_broken_description());
    }

    /**
     * is_broken() is answered once per config data: asked again it reads nothing, and new config is read afresh.
     */
    public function test_is_broken_is_memoised_per_config(): void {
        global $DB;

        // The one-course source reads its course on every check, unlike the missing-id memo.
        $course = $this->getDataGenerator()->create_course();
        $condition = $this->condition($this->config(['c1'], [
            'source' => competency_proficiency::SOURCE_COURSE,
            'courseid' => (int) $course->id,
        ]));
        $this->assertFalse($condition->is_broken());
        $before = $DB->perf_get_reads();
        $this->assertFalse($condition->is_broken());
        $this->assertSame(0, $DB->perf_get_reads() - $before);

        // The control: the same instance with new config is answered afresh.
        $condition->set_config_data(['competencyids' => [999999]] + $this->config([]));
        $this->assertTrue($condition->is_broken());
    }

    /**
     * The unconfigured condition is offered only while competencies are enabled.
     */
    public function test_not_offered_while_competencies_disabled(): void {
        set_config('enabled', 1, 'core_competency');
        $this->assertFalse(competency_proficiency::get_instance()->is_broken());

        set_config('enabled', 0, 'core_competency');
        $this->assertTrue(competency_proficiency::get_instance()->is_broken());
        // A configured one keeps evaluating the stored ratings.
        $this->assertFalse($this->condition($this->config(['c1']))->is_broken());
    }

    /**
     * Config data is cleaned, and the course kept only for the one-course source.
     */
    public function test_retrieve_config_data(): void {
        $formdata = (object) [
            'id' => 1,
            'sortorder' => 0,
            'competencyids' => ['5', '5', '7', '0'],
            'match' => '2',
            'state' => '1',
            'source' => (string) competency_proficiency::SOURCE_GLOBAL,
            'courseid' => '9',
        ];
        $expected = [
            'competencyids' => [5, 7],
            'match' => 2,
            'state' => 1,
            'source' => competency_proficiency::SOURCE_GLOBAL,
            'courseid' => 0,
        ];
        $this->assertSame($expected, competency_proficiency::retrieve_config_data($formdata));

        $formdata->source = (string) competency_proficiency::SOURCE_COURSE;
        $this->assertSame(9, competency_proficiency::retrieve_config_data($formdata)['courseid']);
    }

    /**
     * Form validation: competencies required, existing and at most the cap; a course for the one-course source.
     */
    public function test_config_form_validate(): void {
        $condition = competency_proficiency::get_instance();
        $valid = $this->config(['c1', 'c2']);
        $this->assertSame([], $condition->config_form_validate($valid));

        $this->assertArrayHasKey('competencyids', $condition->config_form_validate(['competencyids' => []] + $valid));
        $this->assertArrayHasKey('competencyids', $condition->config_form_validate(['competencyids' => [999999]] + $valid));
        // Ids beyond the cap are refused for the cap, before any of them is looked up.
        $toomany = range(1, competency_proficiency::MAX_COMPETENCIES + 1);
        $errors = $condition->config_form_validate(['competencyids' => $toomany] + $valid);
        $max = get_string('competencyids_max', 'tool_dynamic_cohorts', competency_proficiency::MAX_COMPETENCIES);
        $this->assertSame($max, $errors['competencyids'] ?? null);
        $nocourse = ['source' => competency_proficiency::SOURCE_COURSE, 'courseid' => 999999] + $valid;
        $this->assertArrayHasKey('courseid', $condition->config_form_validate($nocourse));
    }

    /**
     * The description names the competencies escaped once and says which rating is read.
     */
    public function test_description(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Course & co']);
        $condition = $this->condition($this->config(['c1'], [
            'source' => competency_proficiency::SOURCE_COURSE,
            'courseid' => (int) $course->id,
            'match' => competency_proficiency::MATCH_ALL,
        ]));
        $description = $condition->get_config_description();

        $this->assertStringContainsString('Framework &amp; co › Competency c1 (c1)', $description);
        $this->assertStringContainsString('"Course &amp; co"', $description);
        $this->assertStringNotContainsString('&amp;amp;', $description);
        $this->assertStringStartsWith('Users proficient in all of these competencies', $description);
    }

    /**
     * Relevance: only evidence that can set a proficiency, and only for a configured competency.
     */
    public function test_is_relevant_event(): void {
        $this->create_users(['user']);
        $userid = (int) $this->users['user']->id;
        $condition = $this->condition($this->config(['c1']));

        $sink = $this->redirectEvents();
        $this->add_evidence($userid, 'c1', evidence::ACTION_LOG);
        $this->add_evidence($userid, 'c2', evidence::ACTION_COMPLETE);
        $this->add_evidence($userid, 'c1', evidence::ACTION_COMPLETE);
        $evidence = fn($event) => $event instanceof \core\event\competency_evidence_created;
        $events = array_values(array_filter($sink->get_events(), $evidence));
        $sink->close();

        $this->assertCount(3, $events);
        $this->assertFalse($condition->is_relevant_event($events[0]), 'A log action sets no proficiency.');
        $this->assertFalse($condition->is_relevant_event($events[1]), 'Another competency.');
        $this->assertTrue($condition->is_relevant_event($events[2]));
    }

    /**
     * Relevance of the one-course source: only evidence given in that course counts.
     */
    public function test_is_relevant_event_for_one_course(): void {
        $this->create_users(['user']);
        $userid = (int) $this->users['user']->id;
        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        $condition = $this->condition($this->config(['c1'], [
            'source' => competency_proficiency::SOURCE_COURSE,
            'courseid' => (int) $coursea->id,
        ]));

        $sink = $this->redirectEvents();
        foreach ([$courseb, $coursea] as $course) {
            \core_competency\api::add_evidence(
                $userid,
                $this->competencies['c1'],
                \core\context\course::instance($course->id),
                evidence::ACTION_COMPLETE,
                'evidence_manualoverride',
                'core_competency'
            );
        }
        $evidence = fn($event) => $event instanceof \core\event\competency_evidence_created;
        $events = array_values(array_filter($sink->get_events(), $evidence));
        $sink->close();

        $this->assertCount(2, $events);
        $this->assertFalse($condition->is_relevant_event($events[0]), 'Evidence given in another course.');
        $this->assertTrue($condition->is_relevant_event($events[1]));
    }

    /**
     * A rating given through core's API adds the user to a real-time rule's cohort at once.
     */
    public function test_rating_processes_the_rule_in_real_time(): void {
        global $DB;

        // The observer is non-internal: its call waits for the commit advanced_testcase would never make.
        $this->preventResetByRollback();
        set_config('realtime', 1, 'tool_dynamic_cohorts');

        $this->create_users(['user']);
        $userid = (int) $this->users['user']->id;
        $cohort = $this->getDataGenerator()->create_cohort();
        $rule = new rule(0, (object) ['name' => 'Rule', 'enabled' => 1, 'realtime' => 1, 'cohortid' => $cohort->id]);
        $rule->save();
        $condition = $this->condition($this->config(['c1']));
        $record = $condition->get_record();
        $record->set('ruleid', $rule->get('id'));
        $record->set('sortorder', 0);
        $record->save();

        $this->add_evidence($userid, 'c1', evidence::ACTION_LOG);
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $userid]));

        $this->add_evidence($userid, 'c1', evidence::ACTION_COMPLETE);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $userid]));
    }
}

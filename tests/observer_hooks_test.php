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
use tool_dynamic_cohorts\fixtures\event_filter_condition;
use tool_dynamic_cohorts\fixtures\event_other_condition;
use tool_dynamic_cohorts\fixtures\testable_observer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/event_filter_condition.php');
require_once(__DIR__ . '/fixtures/event_other_condition.php');
require_once(__DIR__ . '/fixtures/testable_observer.php');

/**
 * Tests of the observer's relevance filter, its once-per-event processing, and the user it processes.
 *
 * The observer is driven directly with the fixture conditions, which discovery never offers.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(observer::class)]
#[CoversClass(condition_base::class)]
final class observer_hooks_test extends \advanced_testcase {
    /**
     * Reset the fixture counter.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        event_filter_condition::$sqlcalls = 0;
    }

    /**
     * Create an enabled real-time rule on a new cohort with the given conditions.
     *
     * @param array $conditions Condition class => config data.
     * @return rule
     */
    private function create_rule(array $conditions): rule {
        $cohort = $this->getDataGenerator()->create_cohort();
        $rule = new rule(0, (object) ['name' => 'Rule', 'enabled' => 1, 'realtime' => 1, 'cohortid' => $cohort->id]);
        $rule->save();

        $sortorder = 0;
        foreach ($conditions as $classname => $configdata) {
            $condition = condition_base::get_instance(0, (object) ['classname' => $classname]);
            $condition->set_config_data($configdata);
            $record = $condition->get_record();
            $record->set('ruleid', $rule->get('id'));
            $record->set('sortorder', $sortorder++);
            $record->save();
        }

        return $rule;
    }

    /**
     * Members of the rule's cohort.
     *
     * @param rule $rule The rule.
     * @return int[] User ids, sorted.
     */
    private function members(rule $rule): array {
        global $DB;

        $userids = array_map('intval', $DB->get_fieldset('cohort_members', 'userid', ['cohortid' => $rule->get('cohortid')]));
        sort($userids);
        return $userids;
    }

    /**
     * A login event for a user, carrying the given username in other.
     *
     * @param \stdClass $user The user who logged in.
     * @param string $username Username to carry.
     * @return \core\event\user_loggedin
     */
    private function login_event(\stdClass $user, string $username): \core\event\user_loggedin {
        return \core\event\user_loggedin::create([
            'userid' => $user->id,
            'objectid' => $user->id,
            'other' => ['username' => $username],
        ]);
    }

    /**
     * A rule is processed only for an event one of its conditions finds relevant.
     */
    public function test_irrelevant_event_does_not_process_the_rule(): void {
        $user = $this->getDataGenerator()->create_user();
        $rule = $this->create_rule([
            event_filter_condition::class => ['userids' => [(int) $user->id], 'relevantusername' => 'match'],
        ]);
        $conditions = [event_filter_condition::get_instance()];

        testable_observer::process_with($this->login_event($user, 'other'), $conditions);
        $this->assertSame([], $this->members($rule));
        $this->assertSame(0, event_filter_condition::$sqlcalls);

        // The control: the same event carrying the configured username processes the rule.
        testable_observer::process_with($this->login_event($user, 'match'), $conditions);
        $this->assertSame([(int) $user->id], $this->members($rule));
    }

    /**
     * A rule whose two condition classes both subscribe to the event is evaluated once, not twice.
     */
    public function test_rule_is_processed_once_per_event(): void {
        $user = $this->getDataGenerator()->create_user();
        $rule = $this->create_rule([
            event_filter_condition::class => ['userids' => [(int) $user->id]],
            event_other_condition::class => ['userids' => [(int) $user->id]],
        ]);

        testable_observer::process_with(
            $this->login_event($user, $user->username),
            [event_filter_condition::get_instance(), event_other_condition::get_instance()]
        );

        // One evaluation builds the SQL of both conditions once each.
        $this->assertSame(2, event_filter_condition::$sqlcalls);
        $this->assertSame([(int) $user->id], $this->members($rule));
    }

    /**
     * A plan event raised by someone else processes the plan's owner.
     *
     * Core's plan events pass no related user; core fills it in from their user context, which is
     * what a learning plan condition relies on to process the right user in real time.
     */
    public function test_plan_event_processes_the_owner_not_the_actor(): void {
        global $USER;

        $this->setAdminUser();
        $owner = $this->getDataGenerator()->create_user();
        $plan = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan(['userid' => $owner->id]);
        $rule = $this->create_rule([event_filter_condition::class => ['userids' => [(int) $owner->id, (int) $USER->id]]]);

        testable_observer::process_with(
            \core\event\competency_plan_created::create_from_plan($plan),
            [event_filter_condition::get_instance()]
        );

        $this->assertSame([(int) $owner->id], $this->members($rule));
    }
}

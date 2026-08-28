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

namespace tool_dynamic_cohorts\privacy;

use context_system;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;
use tool_dynamic_cohorts\condition;
use tool_dynamic_cohorts\rule;

/**
 * Unit tests for the privacy provider.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_dynamic_cohorts\privacy\provider::class)]
final class provider_test extends \advanced_testcase {
    /**
     * Create one rule and one condition owned by the given user.
     *
     * Both persistents stamp usermodified from the logged-in user, so the caller must be
     * logged in as that user before this runs.
     *
     * @param string $rulename Name to give the rule.
     * @return rule The created rule.
     */
    protected function create_rule_and_condition(string $rulename): rule {
        $cohort = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object) ['name' => $rulename, 'cohortid' => $cohort->id]);
        $rule->save();

        $condition = new condition(0, (object) [
            'ruleid' => $rule->get('id'),
            'classname' => 'tool_dynamic_cohorts\\local\\tool_dynamic_cohorts\\condition\\user_profile',
            'sortorder' => 0,
            'configdata' => '{}',
        ]);
        $condition->save();

        return $rule;
    }

    /**
     * A user who owns a rule is reported against the system context.
     *
     * @return void
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->create_rule_and_condition('Rule of the first user');

        // Cast: contextlist ids come back from the DB as strings under both drivers.
        $contexts = array_map('intval', provider::get_contexts_for_userid((int) $user->id)->get_contextids());
        $this->assertSame([(int) context_system::instance()->id], $contexts);

        // Control: a user who owns nothing is reported against no context at all.
        $other = $this->getDataGenerator()->create_user();
        $this->assertSame([], provider::get_contexts_for_userid((int) $other->id)->get_contextids());
    }

    /**
     * Only owners appear in the userlist, and only for the system context.
     *
     * @return void
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->create_rule_and_condition('Rule of the first user');

        $userlist = new userlist(context_system::instance(), 'tool_dynamic_cohorts');
        provider::get_users_in_context($userlist);

        $this->assertContains((int) $user->id, $userlist->get_userids());
        $this->assertNotContains((int) $other->id, $userlist->get_userids());
    }

    /**
     * The export carries BOTH the rules and the conditions.
     *
     * Regression test. export_user_data() used to call export_data() twice on the same
     * subcontext, and moodle_content_writer resolves a subcontext to a single data.json
     * written with file_put_contents — so the second call overwrote the first and the
     * rules were silently missing from the export of any user who owned both.
     *
     * @return void
     */
    public function test_export_user_data_keeps_rules_and_conditions(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->create_rule_and_condition('Exported rule');

        $context = context_system::instance();
        provider::export_user_data(new approved_contextlist(
            $user,
            'tool_dynamic_cohorts',
            [$context->id]
        ));

        $data = writer::with_context($context)->get_data([get_string('pluginname', 'tool_dynamic_cohorts')]);

        $this->assertCount(1, $data->rules);
        $this->assertSame('Exported rule', $data->rules[0]['rulename']);

        // The control: the conditions the overwrite used to leave behind are still there.
        $this->assertCount(1, $data->conditions);
        $this->assertSame('Exported rule', $data->conditions[0]['rulename']);
    }

    /**
     * Deleting one user's data leaves every other user's data alone.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $this->setUser($user);
        $this->create_rule_and_condition('Rule of the first user');

        $this->setUser($other);
        $this->create_rule_and_condition('Rule of the second user');

        provider::delete_data_for_user(new approved_contextlist(
            $user,
            'tool_dynamic_cohorts',
            [context_system::instance()->id]
        ));

        $this->assertSame(0, $DB->count_records('tool_dynamic_cohorts', ['usermodified' => $user->id]));
        $this->assertSame(0, $DB->count_records('tool_dynamic_cohorts_c', ['usermodified' => $user->id]));

        /* Control: the other user is untouched. Without it this test would still pass if
           delete_data_for_user() cleared the column for everyone. */
        $this->assertSame(1, $DB->count_records('tool_dynamic_cohorts', ['usermodified' => $other->id]));
        $this->assertSame(1, $DB->count_records('tool_dynamic_cohorts_c', ['usermodified' => $other->id]));
    }

    /**
     * Deleting an approved userlist clears exactly the listed users.
     *
     * @return void
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $this->setUser($user);
        $this->create_rule_and_condition('Rule of the first user');

        $this->setUser($other);
        $this->create_rule_and_condition('Rule of the second user');

        provider::delete_data_for_users(new approved_userlist(
            context_system::instance(),
            'tool_dynamic_cohorts',
            [$user->id]
        ));

        $this->assertSame(0, $DB->count_records('tool_dynamic_cohorts', ['usermodified' => $user->id]));
        $this->assertSame(1, $DB->count_records('tool_dynamic_cohorts', ['usermodified' => $other->id]));
    }

    /**
     * Deleting everything in the system context clears every owner.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $this->setUser($user);
        $this->create_rule_and_condition('Rule of the first user');

        $this->setUser($other);
        $this->create_rule_and_condition('Rule of the second user');

        provider::delete_data_for_all_users_in_context(context_system::instance());

        $this->assertSame(0, $DB->count_records_select('tool_dynamic_cohorts', 'usermodified <> 0'));
        $this->assertSame(0, $DB->count_records_select('tool_dynamic_cohorts_c', 'usermodified <> 0'));

        // The rules themselves survive: only the ownership stamp is cleared.
        $this->assertSame(2, $DB->count_records('tool_dynamic_cohorts'));
    }
}

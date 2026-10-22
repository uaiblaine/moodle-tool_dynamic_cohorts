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

use externallib_advanced_testcase;
use tool_dynamic_cohorts\rule;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for matching users external APIs .
 *
 * @package    tool_dynamic_cohorts
 * @copyright  2024 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_dynamic_cohorts\external\rules::class)]
final class rules_test extends externallib_advanced_testcase {
    /**
     * Test exception if rule is not exist.
     */
    public function test_delete_rules_exception_on_invalid_rule(): void {
        $this->resetAfterTest();

        $this->setAdminUser();
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage('Rule does not exist. ID: 777');

        rules::delete_rules([777]);
    }

    /**
     * Test required permissions.
     */
    public function test_delete_rules_permissions(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        $this->expectExceptionMessage('Sorry, but you do not currently have permissions to do that (Manage rules).');

        rules::delete_rules([777]);
    }

    /**
     * Each rule is decided in its cohort's context: one refused rule leaves every rule in place.
     */
    public function test_delete_rules_checks_each_rule_in_its_cohort_context(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $category = $generator->create_category();
        $categorycontext = \core\context\coursecat::instance($category->id);
        $systemcohort = $generator->create_cohort();
        $categorycohort = $generator->create_cohort(['contextid' => $categorycontext->id]);

        $systemrule = new rule(0, (object)['name' => 'System rule', 'cohortid' => $systemcohort->id]);
        $systemrule->save();
        $categoryrule = new rule(0, (object)['name' => 'Category rule', 'cohortid' => $categorycohort->id]);
        $categoryrule->save();
        $spare = new rule(0, (object)['name' => 'Spare rule', 'cohortid' => $generator->create_cohort()->id]);
        $spare->save();

        // A site-level rule manager whose capability is prohibited in one category.
        $user = $generator->create_user();
        $roleid = $generator->create_role();
        $systemcontext = \core\context\system::instance();
        assign_capability('tool/dynamic_cohorts:manage', CAP_ALLOW, $roleid, $systemcontext->id);
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $systemcontext->id);
        assign_capability('tool/dynamic_cohorts:manage', CAP_PROHIBIT, $roleid, $categorycontext->id);
        role_assign($roleid, $user->id, $systemcontext->id);
        $this->setUser($user);

        try {
            rules::delete_rules([$systemrule->get('id'), $categoryrule->get('id')]);
            $this->fail('A rule in a context where the capability is prohibited was deleted.');
        } catch (\required_capability_exception $exception) {
            $this->assertTrue($DB->record_exists(rule::TABLE, ['id' => $systemrule->get('id')]));
            $this->assertTrue($DB->record_exists(rule::TABLE, ['id' => $categoryrule->get('id')]));
        }

        // The control: the same user may delete a rule outside that category.
        rules::delete_rules([$spare->get('id')]);
        $this->assertFalse($DB->record_exists(rule::TABLE, ['id' => $spare->get('id')]));
    }

    /**
     * Test exception thrown on deleting invalid rule.
     */
    public function test_exception_delete_rules_when_one_is_invalid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $rule = new rule(0, (object)['name' => 'Test rule 1']);
        $rule->save();

        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage('Rule does not exist. ID: 777');

        rules::delete_rules([$rule->get('id'), 777]);
    }

    /**
     * Test rules are not deleted if one is invalid.
     */
    public function test_delete_rules_keep_rules_when_one_is_invalid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $rule1 = new rule(0, (object)['name' => 'Test rule 1']);
        $rule1->save();

        $rule2 = new rule(0, (object)['name' => 'Test rule 2']);
        $rule2->save();

        $this->assertCount(2, rule::get_records());

        try {
            rules::delete_rules([$rule1->get('id'), $rule2->get('id'), 777]);
        } catch (\invalid_parameter_exception $exception) {
            $this->assertSame(
                'Invalid parameter value detected (Rule does not exist. ID: 777)',
                $exception->getMessage()
            );
            $this->assertCount(2, rule::get_records());
        }
    }

    /**
     * Test exception if rule is not exist.
     */
    public function test_toggle_status_exception_on_invalid_rule(): void {
        $this->resetAfterTest();

        $this->setAdminUser();
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage('Rule does not exist. ID: 777');

        rules::toggle_status(777);
    }

    /**
     * Test required permissions.
     */
    public function test_toggle_status_permissions(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        $this->expectExceptionMessage('Sorry, but you do not currently have permissions to do that (Manage rules).');

        rules::toggle_status(777);
    }

    /**
     * Test exception is thrown trying to toggle a broken rule
     */
    public function test_exception_toggle_status_on_broken_rule(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $rule = new rule(0, (object)['name' => 'Test rule 1']);
        $rule->set('broken', 1);
        $rule->save();

        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage(
            get_string('cannotenablebrokenrule', 'tool_dynamic_cohorts') . ' ID: ' . $rule->get('id')
        );

        rules::toggle_status($rule->get('id'));
    }

    /**
     * Test toggling status on rule.
     */
    public function test_toggle_status_on_rule(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $rule = new rule(0, (object)['name' => 'Test rule 1']);
        $rule->save();

        $this->assertFalse($rule->is_enabled());

        rules::toggle_status($rule->get('id'));
        $rule = rule::get_record(['id' => $rule->get('id')]);
        $this->assertTrue($rule->is_enabled());

        rules::toggle_status($rule->get('id'));
        $rule = rule::get_record(['id' => $rule->get('id')]);
        $this->assertFalse($rule->is_enabled());
    }
}

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

use core_reportbuilder\system_report_factory;
use PHPUnit\Framework\Attributes\CoversClass;
use tool_dynamic_cohorts\reportbuilder\local\systemreports\matching_users;
use tool_dynamic_cohorts\rule;

/**
 * The matching-users report shows a category manager only the identity fields core would show.
 *
 * A rule may match any user of the site, and the report can be filtered and downloaded, so its
 * columns are what decides which personal data of those users a category manager reads.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(matching_users::class)]
final class matching_users_identity_test extends \advanced_testcase {
    /** @var string[] The identity columns the report used to show everywhere. */
    private const FIXED = ['user:username', 'user:email', 'user:idnumber'];

    /**
     * The report's column and filter identifiers for a rule, built as the current user.
     *
     * @param rule $rule The rule.
     * @param \core\context $context Context the report is created in.
     * @return array Two lists: 'columns' and 'filters'.
     */
    private function report_fields(rule $rule, \core\context $context): array {
        $report = system_report_factory::create(
            matching_users::class,
            $context,
            'tool_dynamic_cohorts',
            '',
            0,
            ['ruleid' => (int) $rule->get('id')]
        );
        return ['columns' => array_keys($report->get_columns()), 'filters' => array_keys($report->get_filters())];
    }

    /**
     * A rule on a new cohort in a context.
     *
     * @param \core\context $context The cohort's context.
     * @return rule
     */
    private function rule_in(\core\context $context): rule {
        $cohort = $this->getDataGenerator()->create_cohort(['contextid' => $context->id]);
        $rule = new rule(0, (object) ['name' => 'Rule', 'cohortid' => $cohort->id]);
        $rule->save();
        return $rule;
    }

    /**
     * In a category: identity fields by core's rules; at the system level: the fixed columns.
     */
    public function test_columns_follow_identity_fields_in_a_category(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        set_config('showuseridentity', 'email');

        $category = \core\context\coursecat::instance($generator->create_category()->id);
        $rule = $this->rule_in($category);

        $roleid = $generator->create_role();
        assign_capability('tool/dynamic_cohorts:manage', CAP_ALLOW, $roleid, $category->id);
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $category->id);
        $manager = $generator->create_user();
        role_assign($roleid, $manager->id, $category->id);

        $identityroleid = $generator->create_role();
        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $identityroleid, $category->id);
        $seer = $generator->create_user();
        role_assign($roleid, $seer->id, $category->id);
        role_assign($identityroleid, $seer->id, $category->id);

        // Without moodle/site:viewuseridentity: the full name only.
        $this->setUser($manager);
        $fields = $this->report_fields($rule, $category);
        $this->assertContains('user:fullnamewithlink', $fields['columns']);
        foreach (self::FIXED as $identifier) {
            $this->assertNotContains($identifier, $fields['columns']);
            $this->assertNotContains($identifier, $fields['filters']);
        }

        // With it: the configured identity field, and still not the others.
        $this->setUser($seer);
        $fields = $this->report_fields($rule, $category);
        $this->assertContains('user:email', $fields['columns']);
        $this->assertContains('user:email', $fields['filters']);
        $this->assertNotContains('user:username', $fields['columns']);
        $this->assertNotContains('user:idnumber', $fields['columns']);

        // The system level keeps its fixed columns.
        $this->setAdminUser();
        $fields = $this->report_fields($this->rule_in(\core\context\system::instance()), \core\context\system::instance());
        foreach (self::FIXED as $identifier) {
            $this->assertContains($identifier, $fields['columns']);
        }
    }
}

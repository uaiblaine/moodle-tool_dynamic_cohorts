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
use tool_dynamic_cohorts\cohort_manager;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_manager;
use tool_dynamic_cohorts\external\rule_conditions;
use tool_dynamic_cohorts\fixtures\hostile_condition;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\cohort_membership;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_enrolment;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_last_login;
use tool_dynamic_cohorts\reportbuilder\local\systemreports\rules;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\rule_form;
use tool_dynamic_cohorts\rule_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once(__DIR__ . '/../fixtures/hostile_condition.php');

/**
 * What reaches the conditions template and the rules report is escaped or cleaned.
 *
 * The template prints a condition's description raw ({{{description}}}), and core's form option
 * templates print option labels raw, so a value a rule's author typed, a cohort name, or a profile
 * field name must arrive in the ESCAPED spelling, and a description from a condition this plugin does
 * not control must arrive cleaned. Each test names the guard it pins.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(condition_manager::class)]
#[CoversClass(condition_base::class)]
#[CoversClass(cohort_manager::class)]
#[CoversClass(rule_manager::class)]
#[CoversClass(rule_conditions::class)]
#[CoversClass(rule_form::class)]
#[CoversClass(\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile::class)]
#[CoversClass(\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_custom_profile::class)]
#[CoversClass(user_last_login::class)]
#[CoversClass(\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_created::class)]
#[CoversClass(user_enrolment::class)]
#[CoversClass(cohort_membership::class)]
#[CoversClass(\tool_dynamic_cohorts\reportbuilder\local\entities\rule_entity::class)]
final class description_escaping_test extends \advanced_testcase {
    /** @var string Namespace of the built-in conditions. */
    private const NS = 'tool_dynamic_cohorts\\local\\tool_dynamic_cohorts\\condition\\';

    /** @var string Markup a hostile author would store. */
    private const MARKUP = '<img src=x onerror=alert(1)>';

    /**
     * Reset, and act as an administrator: option lists come from capability-aware core APIs.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * A configured condition instance.
     *
     * @param string $classname Condition class name.
     * @param array $configdata Config data.
     * @return condition_base
     */
    private function condition(string $classname, array $configdata): condition_base {
        $condition = condition_base::get_instance(0, (object) ['classname' => $classname]);
        $condition->set_config_data($configdata);
        return $condition;
    }

    /**
     * A rule on a new cohort holding one stored condition.
     *
     * @param string $classname Condition class name.
     * @param string $configdata Stored config data, JSON.
     * @return rule
     */
    private function rule_with(string $classname, string $configdata): rule {
        $cohort = $this->getDataGenerator()->create_cohort();
        $rule = new rule(0, (object) ['name' => 'Rule', 'cohortid' => $cohort->id]);
        $rule->save();
        $record = new \tool_dynamic_cohorts\condition(0, (object) [
            'ruleid' => $rule->get('id'),
            'classname' => $classname,
            'configdata' => $configdata,
            'sortorder' => 0,
        ]);
        $record->save();
        return $rule;
    }

    /**
     * The rule form for a new rule on the system page.
     *
     * @return rule_form
     */
    private function system_rule_form(): rule_form {
        $ajaxdata = ['id' => 0, 'contextid' => \core\context\system::instance()->id];
        return new rule_form(null, null, 'post', '', [], true, $ajaxdata, true);
    }

    /**
     * Descriptions of a rule's conditions as the rule form receives them.
     *
     * @param rule $rule The rule.
     * @return string[]
     */
    private function form_descriptions(rule $rule): array {
        $data = rule_manager::build_data_for_form($rule);
        return array_column(json_decode($data['conditionjson'], true), 'description');
    }

    /**
     * Descriptions of a rule's conditions as the conditions web service returns them.
     *
     * @param rule $rule The rule.
     * @return string[]
     */
    private function service_descriptions(rule $rule): array {
        return array_column(rule_conditions::get_conditions((int) $rule->get('id')), 'description');
    }

    /**
     * A text value the author typed is escaped in the description (fields_trait::get_field_value_text()).
     */
    public function test_field_value_is_escaped(): void {
        $description = $this->condition(self::NS . 'user_profile', [
            'profilefield' => 'firstname',
            'firstname_operator' => condition_base::TEXT_IS_EQUAL_TO,
            'firstname_value' => self::MARKUP . ' & co',
        ])->get_config_description();

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt; &amp; co', $description);
        $this->assertStringNotContainsString('<img', $description);
    }

    /**
     * A custom profile field's name and menu option labels reach the description escaped, once.
     */
    public function test_custom_profile_field_name_and_options_are_formatted(): void {
        global $DB;

        $DB->insert_record('user_info_field', (object) [
            'shortname' => 'pets',
            'name' => 'Pets & co',
            'datatype' => 'menu',
            'description' => '',
            'categoryid' => 0,
            'visible' => PROFILE_VISIBLE_ALL,
            'param1' => "Cats & dogs\nFish",
        ]);

        $description = $this->condition(self::NS . 'user_custom_profile', [
            'profilefield' => 'profile_field_pets',
            'profile_field_pets_operator' => condition_base::TEXT_IS_EQUAL_TO,
            'profile_field_pets_value' => 'Cats & dogs',
        ])->get_config_description();

        // The name and the option label, each escaped once.
        $this->assertStringContainsString('Pets &amp; co', $description);
        $this->assertStringContainsString('Cats &amp; dogs', $description);
        $this->assertStringNotContainsString('&amp;amp;', $description);
    }

    /**
     * A stored period type the form never offered is escaped in the last-login description.
     */
    public function test_last_login_period_is_escaped(): void {
        $description = $this->condition(self::NS . 'user_last_login', [
            'operator' => user_last_login::OPERATOR_IN_LAST,
            'period_value' => 1,
            'period_type' => self::MARKUP,
        ])->get_config_description();

        $this->assertStringContainsString('&lt;img', $description);
        $this->assertStringNotContainsString('<img', $description);
    }

    /**
     * The same for the user-created condition, which describes the period on its own.
     */
    public function test_user_created_period_is_escaped(): void {
        $description = $this->condition(self::NS . 'user_created', [
            'operator' => user_last_login::OPERATOR_IN_LAST,
            'period_value' => 1,
            'period_type' => self::MARKUP,
        ])->get_config_description();

        $this->assertStringContainsString('&lt;img', $description);
        $this->assertStringNotContainsString('<img', $description);
    }

    /**
     * An unknown enrolment method is escaped in the broken description that names it.
     */
    public function test_enrolment_method_is_escaped(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $condition = $this->condition(self::NS . 'user_enrolment', [
            'operator' => user_enrolment::OPERATOR_ENROLLED,
            'courseid' => $course->id,
            'roleid' => (int) $DB->get_field('role', 'id', ['shortname' => 'student']),
            'enrolmethod' => self::MARKUP,
        ]);

        // The precondition: this is the branch that names the method.
        $this->assertTrue($condition->is_broken());
        $description = $condition->get_broken_description();
        $this->assertStringContainsString('&lt;img', $description);
        $this->assertStringNotContainsString('<img', $description);
    }

    /**
     * The base broken description, the stored JSON, is escaped (condition_base::get_broken_description()).
     */
    public function test_base_broken_description_is_escaped(): void {
        $condition = $this->condition(hostile_condition::class, ['broken' => 1, 'note' => self::MARKUP]);

        $this->assertTrue($condition->is_broken());
        $this->assertStringContainsString('&lt;img', $condition->get_broken_description());
        $this->assertStringNotContainsString('<img', $condition->get_broken_description());
    }

    /**
     * A cohort name carrying markup is escaped in the cohort membership description.
     */
    public function test_cohort_name_is_formatted_in_the_description(): void {
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Staff & co ' . self::MARKUP]);

        $description = $this->condition(self::NS . 'cohort_membership', [
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_MEMBER_OF,
            'cohort_membership_value' => [$cohort->id],
        ])->get_config_description();

        $this->assertStringContainsString('Staff &amp; co', $description);
        $this->assertStringNotContainsString('<img', $description);
    }

    /**
     * The rule form's cohort options carry the cohort name escaped: core prints option labels raw.
     */
    public function test_cohort_name_is_formatted_in_the_rule_form(): void {
        $this->getDataGenerator()->create_cohort(['name' => 'Staff & co ' . self::MARKUP]);

        $form = $this->system_rule_form();
        $method = new \ReflectionMethod($form, 'get_cohort_options');
        $labels = implode('|', $method->invoke($form));

        $this->assertStringContainsString('Staff &amp; co', $labels);
        $this->assertStringNotContainsString('<img', $labels);
    }

    /**
     * Every producer of the template's data cleans a description it cannot trust.
     *
     * The fixture stands for a condition of another plugin, which escapes nothing. The badge markup
     * the interests condition uses survives the cleaning; the event handler does not.
     */
    public function test_producers_clean_the_description(): void {
        $configdata = json_encode(['description' => self::MARKUP . '<span class="badge">kept</span>']);
        $rule = $this->rule_with(hostile_condition::class, $configdata);

        $producers = ['rule form data' => $this->form_descriptions($rule), 'web service' => $this->service_descriptions($rule)];
        foreach ($producers as $where => $descriptions) {
            $this->assertCount(1, $descriptions, $where);
            $this->assertStringNotContainsString('onerror', $descriptions[0], $where);
            $this->assertStringContainsString('<span class="badge">kept</span>', $descriptions[0], $where);
        }
    }

    /**
     * A condition whose class is gone shows its stored JSON escaped, in both producers.
     */
    public function test_missing_class_shows_escaped_config(): void {
        $rule = $this->rule_with('tool_dynamic_cohorts\\not_installed', json_encode(['note' => '<b>bold</b>']));

        $producers = ['rule form data' => $this->form_descriptions($rule), 'web service' => $this->service_descriptions($rule)];
        foreach ($producers as $where => $descriptions) {
            $this->assertStringContainsString('&lt;b&gt;bold', $descriptions[0], $where);
            $this->assertStringNotContainsString('<b>', $descriptions[0], $where);
        }
    }

    /**
     * The rule form cleans descriptions it renders from the JSON the browser posted back.
     */
    public function test_rule_form_cleans_posted_descriptions(): void {
        $form = $this->system_rule_form();
        $form->set_data(['conditionjson' => json_encode([[
            'id' => 0,
            'sortorder' => 0,
            'classname' => self::NS . 'user_last_login',
            'configdata' => '{"operator":1}',
            'name' => 'Last login',
            'description' => self::MARKUP . 'posted',
            'broken' => false,
        ]])]);

        // The conditions table only: the hidden conditionjson field carries the posted JSON, escaped.
        $this->assertSame(1, preg_match('~<div id="conditions">(.*?)</table>~s', $form->render(), $matches));
        $this->assertStringContainsString('posted', $matches[1]);
        $this->assertStringNotContainsString('onerror', $matches[1]);
    }

    /**
     * The rules report prints a rule's name and description formatted.
     */
    public function test_rule_name_and_description_are_formatted(): void {
        global $PAGE;

        // The dynamic table reads the page URL, as on index.php.
        $PAGE->set_url('/admin/tool/dynamic_cohorts/index.php');
        $cohort = $this->getDataGenerator()->create_cohort();
        $rule = new rule(0, (object) ['name' => 'Name & co', 'description' => 'About & more', 'cohortid' => $cohort->id]);
        $rule->save();

        $html = system_report_factory::create(rules::class, \core\context\system::instance(), 'tool_dynamic_cohorts')->output();

        $this->assertStringContainsString('Name &amp; co', $html);
        $this->assertStringNotContainsString('Name & co', $html);
        $this->assertStringContainsString('About &amp; more', $html);
        $this->assertStringNotContainsString('About & more', $html);
    }
}

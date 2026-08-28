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

use context_user;
use core_tag_tag;
use tool_dynamic_cohorts\condition_base;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for user_profile_interests condition class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile_interests::class)]
final class user_profile_interests_test extends \advanced_testcase {
    /**
     * Set up the test case.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Get condition instance for testing.
     *
     * @param array $configdata Config data to be set.
     * @return condition_base
     */
    protected function get_condition(array $configdata = []): condition_base {
        $condition = condition_base::get_instance(0, (object) [
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile_interests',
        ]);
        $condition->set_config_data($configdata);

        return $condition;
    }

    /**
     * Create some user interest tags for testing.
     *
     * @return array List of tags indexed by tag rawname.
     */
    protected function create_tags(): array {
        $user = $this->getDataGenerator()->create_user();

        core_tag_tag::set_item_tags(
            'core',
            'user',
            $user->id,
            context_user::instance($user->id),
            [
                'Cats',
                'Dogs',
                'Horses',
            ]
        );

        $tags = core_tag_tag::get_item_tags('core', 'user', $user->id);
        $this->assertCount(3, $tags);

        // Index tags by rawname and return.
        return array_column($tags, null, 'rawname');
    }

    /**
     * Test retrieving of config data.
     */
    public function test_retrieving_configdata(): void {
        $tags = $this->create_tags();
        $cats = $tags['Cats']->id;
        $horses = $tags['Horses']->id;

        $formdata = [
            'tags_operator' => condition_base::TEXT_CONTAINS,
            'tags' => [$cats, $horses],
        ];

        $actual = $this->get_condition()::retrieve_config_data((object) $formdata);
        $this->assertEquals($formdata, $actual);
    }

    /**
     * Test setting and getting config data.
     */
    public function test_set_and_get_configdata(): void {
        $tags = $this->create_tags();
        $cats = $tags['Cats']->id;
        $horses = $tags['Horses']->id;

        $configdata = [
            'tags_operator' => condition_base::TEXT_CONTAINS,
            'tags' => [$cats, $horses],
        ];

        $condition = $this->get_condition($configdata);

        $this->assertEquals($configdata, $condition->get_config_data());
    }

    /**
     * Test getting config description.
     */
    public function test_config_description(): void {
        $tags = $this->create_tags();

        $condition = $this->get_condition([
            'tags_operator' => condition_base::TEXT_CONTAINS,
            'tags' => [$tags['Dogs']->id],
        ]);
        $this->assertEquals(0, strpos($condition->get_config_description(), 'Users with interests containing the following tags '));
        $this->assertStringContainsString('Dogs', $condition->get_config_description());
        $this->assertStringNotContainsString('Horses', $condition->get_config_description());

        $condition = $this->get_condition([
            'tags_operator' => condition_base::TEXT_DOES_NOT_CONTAIN,
            'tags' => [$tags['Horses']->id],
        ]);
        $this->assertEquals(
            0,
            strpos($condition->get_config_description(), 'Users with interests not containing the following tags ')
        );
        $this->assertStringContainsString('Horses', $condition->get_config_description());
        $this->assertStringNotContainsString('Dogs', $condition->get_config_description());
    }

    /**
     * Test is broken.
     */
    public function test_is_broken(): void {
        $tags = $this->create_tags();
        $cats = $tags['Cats']->id;

        $condition = condition_base::get_instance(0, (object) [
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile_interests',
        ]);
        $this->assertFalse($condition->is_broken());

        // Valid tags and operator.
        $condition = $this->get_condition([
            'tags_operator' => condition_base::TEXT_CONTAINS,
            'tags' => [$cats],
        ]);
        $this->assertFalse($condition->is_broken());

        // Valid tags and operator.
        $condition = $this->get_condition([
            'tags_operator' => condition_base::TEXT_DOES_NOT_CONTAIN,
            'tags' => [$cats],
        ]);
        $this->assertFalse($condition->is_broken());

        // Invalid tags.
        $condition = $this->get_condition([
            'tags_operator' => condition_base::TEXT_CONTAINS,
            'tags' => [],
        ]);
        $this->assertTrue($condition->is_broken());

        // Invalid operator.
        $condition = $this->get_condition([
            'tags_operator' => condition_base::TEXT_IS_EMPTY,
            'tags' => [$cats],
        ]);
        $this->assertTrue($condition->is_broken());
    }

    /**
     * Test getting correct SQL.
     */
    public function test_get_sql_data(): void {
        global $DB;

        // Create some tags which also creates a user with those tags.
        $tags = $this->create_tags();
        $cats = $tags['Cats']->id;
        $dogs = $tags['Dogs']->id;
        $horses = $tags['Horses']->id;

        // Create extra users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Confirm one user has all three tags.
        $configdata = [
            'tags_operator' => condition_base::TEXT_CONTAINS,
            'tags' => [$cats],
        ];
        $condition = $this->get_condition($configdata)->get_sql();
        $sql = "SELECT u.id FROM {user} u {$condition->get_join()} WHERE {$condition->get_where()}";
        $result = $DB->get_records_sql($sql, $condition->get_params());
        $this->assertCount(1, $result);
        $id = reset($result)->id;

        $configdata['tags'] = [$dogs];
        $condition = $this->get_condition($configdata)->get_sql();
        $sql = "SELECT u.id FROM {user} u {$condition->get_join()} WHERE {$condition->get_where()}";
        $result = $DB->get_records_sql($sql, $condition->get_params());
        $this->assertCount(1, $result);
        $this->assertSame($id, reset($result)->id);

        $configdata['tags'] = [$horses];
        $condition = $this->get_condition($configdata)->get_sql();
        $sql = "SELECT u.id FROM {user} u {$condition->get_join()} WHERE {$condition->get_where()}";
        $result = $DB->get_records_sql($sql, $condition->get_params());
        $this->assertCount(1, $result);
        $this->assertSame($id, reset($result)->id);

        // Confirm the user is not in the result set when using the TEXT_DOES_NOT_CONTAIN operator.
        $configdata = [
            'tags_operator' => condition_base::TEXT_DOES_NOT_CONTAIN,
            'tags' => [$cats, $dogs, $horses],
        ];
        $condition = $this->get_condition($configdata)->get_sql();
        $sql = "SELECT u.id FROM {user} u {$condition->get_join()} WHERE {$condition->get_where()}";
        $result = $DB->get_records_sql($sql, $condition->get_params());
        $this->assertGreaterThanOrEqual(2, count($result));
        $this->assertNotContains($id, array_column($result, 'id'));
        $this->assertContains($user1->id, array_column($result, 'id'));
        $this->assertContains($user2->id, array_column($result, 'id'));

        // Add some interest tags to the users.
        core_tag_tag::add_item_tag(
            'core',
            'user',
            $user1->id,
            context_user::instance($user1->id),
            'Cats'
        );
        core_tag_tag::add_item_tag(
            'core',
            'user',
            $user2->id,
            context_user::instance($user2->id),
            'Dogs'
        );

        // Confirm there are three users where each user has at least one of the tags.
        $configdata = [
            'tags_operator' => condition_base::TEXT_CONTAINS,
            'tags' => [$cats, $dogs, $horses],
        ];
        $condition = $this->get_condition($configdata)->get_sql();
        $sql = "SELECT DISTINCT u.id FROM {user} u {$condition->get_join()} WHERE {$condition->get_where()}";
        $result = $DB->get_records_sql($sql, $condition->get_params());
        $this->assertCount(3, $result);
        $this->assertContains($id, array_column($result, 'id'));
        $this->assertContains($user1->id, array_column($result, 'id'));
        $this->assertContains($user2->id, array_column($result, 'id'));
    }

    /**
     * Test events that the condition is listening to.
     */
    public function test_get_events(): void {
        $this->assertEquals([
            '\core\event\user_created',
            '\core\event\user_updated',
        ], $this->get_condition()->get_events());
    }
}

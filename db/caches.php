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

/**
 * Caches for the plugin.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


$definitions = [
    /* 'simpledata' is a deliberate deviation from its literal contract on these two, which
       hold arrays of core\persistent objects rather than scalars. Dropping it was tried and
       reverted: it makes static acceleration clone on every get, which lands on the hot path
       of the wildcard observer in db/events.php, and it fixes no live defect — the only
       mutation of a cached rule is mark_broken(), which writes through to the database in
       the same call, so the shared reference and the stored row never disagree. Two tests
       (rule_manager_test::test_get_rules_with_condition, rule_test::test_condition_records_get_cached)
       assert the identity semantics this flag gives. */
    'rulesconditions' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simpledata' => true,
        'staticacceleration' => true,
        'invalidationevents' => [
            'ruleschanged',
            'conditionschanged',
        ],
    ],
    'conditionrecords' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simpledata' => true,
        'staticacceleration' => true,
        'invalidationevents' => [
            'ruleschanged',
            'conditionschanged',
        ],
    ],
    'matchinguserscount' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simpledata' => true,
        'staticacceleration' => true,
    ],
];

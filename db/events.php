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
 * Plugin event observers are registered here.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/*
 * 'internal' => false matters here. core\event\manager::init_all_observers() defaults a
 * missing 'internal' to TRUE, and process_buffers() defers only non-internal observers
 * while a transaction is open — so without this flag the callback ran inside whatever
 * transaction the triggering code held. That callback is not small: observer::process_event
 * runs a full SELECT over {user} with every condition's joins and then writes cohort
 * membership, on every matching event, with realtime processing on by default. It
 * lengthened unrelated transactions site-wide, and a write failure inside one poisons the
 * whole transaction on PostgreSQL. Note this does NOT make observer failures visible:
 * manager.php catches \Exception from observers and downgrades it to debugging() either
 * way. Observer definitions are cached, so a version.php bump ships with any change here.
 */
$observers = [
    [
        'eventname' => '*',
        'internal' => false,
        'callback' => '\tool_dynamic_cohorts\observer::process_event',
    ],
];

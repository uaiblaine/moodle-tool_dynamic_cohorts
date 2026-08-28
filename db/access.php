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
 * Plugin capabilities are defined here.
 *
 * @package     tool_dynamic_cohorts
 * @category    access
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    /*
     * RISK_PERSONAL: this capability is the only check on the matching-users report
     * (reportbuilder\local\systemreports\matching_users::can_view()), which lists every
     * user a rule selects together with their username, email and idnumber, and is
     * explicitly downloadable. The same data is reachable through the web services in
     * db/services.php, all of which this capability alone authorises. Without the flag
     * the Define roles screen offers no warning when it is granted to a non-admin role.
     * Core's precedent for the same exposure is moodle/user:viewalldetails.
     * Archetypes stay empty on purpose: admin-only by default.
     */
    'tool/dynamic_cohorts:manage' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
        ],
    ],
];

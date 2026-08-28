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
 * Rules page.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\notification;
use core_reportbuilder\system_report_factory;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\reportbuilder\local\systemreports\rules;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_dynamic_cohorts_rules');

/* The "add a rule" control opens a modal (amd/src/manage_rules.js initRuleAdd), so its
   href is only the no-JS fallback. It used to point at edit.php, which upstream deleted
   in 94e7a8e when the form moved into the modal — so the fallback was a 404. Point it at
   this page, which is what every report builder action in
   reportbuilder\local\systemreports\rules::add_actions() already does. */
$manageurl = new moodle_url('/admin/tool/dynamic_cohorts/index.php');

/* Collect both conditions before emitting, rather than breaking out of the loop on the
   first hit. The original broke on whichever it met first, so a site with broken rules
   never saw the realtime warning at all — and the get_config() call sat inside the loop,
   re-reading the setting once per rule. */
$globalrealtime = get_config('tool_dynamic_cohorts', 'realtime');
$hasbroken = false;
$hasrealtime = false;

foreach (rule::get_records() as $rule) {
    $hasbroken = $hasbroken || $rule->is_broken();
    $hasrealtime = $hasrealtime || ($rule->is_realtime() && !$globalrealtime);

    if ($hasbroken && $hasrealtime) {
        break;
    }
}

if ($hasbroken) {
    notification::warning(get_string('brokenruleswarning', 'tool_dynamic_cohorts'));
}

if ($hasrealtime) {
    notification::warning(get_string('realtimedisabledglobally', 'tool_dynamic_cohorts'));
}

$report = system_report_factory::create(rules::class, context_system::instance(), 'tool_dynamic_cohorts');

$PAGE->requires->js_call_amd('tool_dynamic_cohorts/manage_rules', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managerules', 'tool_dynamic_cohorts'));
echo $OUTPUT->render_from_template('tool_dynamic_cohorts/button', [
    'url' => $manageurl->out(),
    'text' => get_string('addrule', 'tool_dynamic_cohorts'),
]);
echo $report->output();
echo $OUTPUT->footer();

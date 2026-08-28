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

use core_form\dynamic_form;
use html_writer;
use moodle_url;
use context;
use context_system;
use dml_missing_record_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * A form for adding/editing rules.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_form extends dynamic_form {
    /**
     * Form definition.
     */
    protected function definition() {
        global $OUTPUT;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', 0);

        $mform->addElement('text', 'name', get_string('name', 'tool_dynamic_cohorts'), 'size="50"');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('name', 'name', 'tool_dynamic_cohorts');

        $mform->addElement(
            'textarea',
            'description',
            get_string('description', 'tool_dynamic_cohorts'),
            ['rows' => 5, 'cols' => 50]
        );
        $mform->addHelpButton('description', 'description', 'tool_dynamic_cohorts');
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement(
            'autocomplete',
            'cohortid',
            get_string('cohortid', 'tool_dynamic_cohorts'),
            $this->get_cohort_options(),
            ['noselectionstring' => get_string('choosedots')]
        );
        $mform->addHelpButton('cohortid', 'cohortid', 'tool_dynamic_cohorts');
        $mform->addRule('cohortid', get_string('required'), 'required', null, 'client');

        $link = html_writer::link(new moodle_url('/cohort/index.php'), get_string('managecohorts', 'tool_dynamic_cohorts'));
        $mform->addElement('static', '', '', $link);

        // Hidden field for storing condition json string.
        $mform->addElement('hidden', 'conditionjson', '', ['id' => 'id_conditionjson']);
        $mform->setType('conditionjson', PARAM_RAW_TRIMMED);

        // A flag to indicate whether the conditions were updated or not.
        $mform->addElement('hidden', 'isconditionschanged', 0, ['id' => 'id_isconditionschanged']);
        $mform->setType('isconditionschanged', PARAM_BOOL);
        $mform->setDefault('isconditionschanged', 0);

        $conditions = ['' => get_string('choosedots')];
        foreach (condition_manager::get_all_conditions() as $class => $condition) {
            $conditions[$class] = $condition->get_name();
        }

        $group = [];
        $group[] = $mform->createElement('select', 'condition', '', $conditions, ['id' => 'id_condition']);
        $group[] = $mform->createElement(
            'button',
            'conditionmodalbutton',
            get_string('addcondition', 'tool_dynamic_cohorts'),
            ['id' => 'id_conditionmodalbutton']
        );
        $mform->addGroup($group, 'conditiongroup', get_string('condition', 'tool_dynamic_cohorts'), ' ', false);

        $mform->addElement(
            'advcheckbox',
            'bulkprocessing',
            get_string('bulkprocessing', 'tool_dynamic_cohorts'),
            get_string('enable'),
            [],
            [0, 1]
        );
        $mform->addHelpButton('bulkprocessing', 'bulkprocessing', 'tool_dynamic_cohorts');

        $mform->addElement(
            'advcheckbox',
            'realtime',
            get_string('realtime', 'tool_dynamic_cohorts'),
            get_string('enable'),
            [],
            [0, 1]
        );
        $mform->addHelpButton('realtime', 'realtime', 'tool_dynamic_cohorts');

        if (!get_config('tool_dynamic_cohorts', 'realtime')) {
            $mform->freeze(['realtime']);
            $realtimeglobal = $OUTPUT->notification(
                get_string('realtimedisabledglobally', 'tool_dynamic_cohorts'),
                'warning',
                false
            );
            $mform->addElement('static', 'realtimeglobal', '', $realtimeglobal);
        }

        $mform->addElement(
            'select',
            'operator',
            get_string('logical_operator', 'tool_dynamic_cohorts'),
            [rule_manager::CONDITIONS_OPERATOR_AND => 'AND', rule_manager::CONDITIONS_OPERATOR_OR => 'OR']
        );
        $mform->addHelpButton('operator', 'logical_operator', 'tool_dynamic_cohorts');
        $mform->setType('operator', PARAM_INT);

        // Dummy element to be able to add conditions table in definition_after_data.
        $mform->addElement('static', 'afterconditions', '', '');
    }

    /**
     * Get a list of all cohorts in the system.
     *
     * @return array
     */
    protected function get_cohort_options(): array {
        $options = ['' => get_string('choosedots')];

        // Retrieve only available cohorts to display in the select.
        foreach (cohort_manager::get_cohorts(true) as $cohort) {
            $options[$cohort->id] = $cohort->name;
        }

        // Add the currently selected cohort as it won't be in the list.
        $cohort = $this->get_default_cohort();
        if (!empty($cohort)) {
            $options[$cohort->id] = $cohort->name;
        }

        return $options;
    }

    /**
     * Gets default cohort to be set into the form.
     *
     * @return stdClass|null
     */
    protected function get_default_cohort(): ?stdClass {
        global $DB;

        $rule = $this->get_rule();

        if (!empty($rule->get('cohortid'))) {
            /* ?: null because get_record() returns FALSE, not null, for a missing row —
               and the return type here is ?stdClass, so the bool raised a TypeError. A rule
               CAN outlive its cohort (rule_manager::process_rule() has its own guard for
               exactly that), and the fatal landed on the edit modal, taking away the one
               screen an admin could use to repoint the rule at a live cohort. */
            return $DB->get_record('cohort', ['id' => $rule->get('cohortid')]) ?: null;
        } else {
            return null;
        }
    }

    /**
     * Gets rule based on form data.
     *
     * @return rule
     */
    protected function get_rule(): rule {
        $ruleid = isset($this->_ajaxformdata['id']) ? (int)$this->_ajaxformdata['id'] : 0;

        if (!empty($ruleid)) {
            $rule = rule::get_record(['id' => $ruleid]);
            if (empty($rule)) {
                throw new dml_missing_record_exception(null);
            }
            return $rule;
        } else {
            return new rule();
        }
    }

    /**
     * Form context.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /**
     * Access control.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('tool/dynamic_cohorts:manage', $this->get_context_for_dynamic_submission());
    }

    /**
     * Process form submission.
     *
     * @return void
     */
    public function process_dynamic_submission() {
        rule_manager::process_form($this->get_data());
    }

    /**
     * Set data.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data(rule_manager::build_data_for_form($this->get_rule()));
    }

    /**
     * Form URL
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/admin/tool/dynamic_cohorts/index.php');
    }

    /**
     * Definition after data is set.
     */
    public function definition_after_data() {
        global $OUTPUT, $PAGE;

        $PAGE->requires->js_call_amd('tool_dynamic_cohorts/condition_form', 'init');

        $mform = $this->_form;
        $conditionjson = $mform->getElementValue('conditionjson');
        $conditions = $OUTPUT->render_from_template('tool_dynamic_cohorts/conditions', [
            'conditions' => json_decode($conditionjson, true),
        ]);

        $mform->insertElementBefore(
            $mform->createElement(
                'html',
                '<div id="conditions">' . $conditions . '</div>'
            ),
            'afterconditions'
        );
    }
}

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

use coding_exception;
use moodle_exception;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Condition form.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', 0);

        $mform->addElement('hidden', 'sortorder');
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', -1);

        $this->get_condition()->config_form_add($mform);
    }

    /**
     * Get condition instance.
     *
     * @return condition_base
     */
    protected function get_condition(): condition_base {
        if (empty($this->_customdata['classname'])) {
            throw new coding_exception('Condition class name is not set');
        }

        $classname = $this->_customdata['classname'];
        $conditions = condition_manager::get_all_conditions(false);
        if (!array_key_exists($classname, $conditions)) {
            throw new moodle_exception('Condition is broken. Invalid condition class.');
        }

        // A copy: get_all_conditions() memoises its instances, and the scope is this form's alone.
        $condition = clone $conditions[$classname];

        /* customdata 'scope' is the category a category-level user writes the condition for
           (rule_access::condition_scope()); the options and the validation follow it. */
        $scope = $this->_customdata['scope'] ?? null;
        if ($scope !== null && !in_array($classname, condition_manager::category_conditions(), true)) {
            throw new moodle_exception('conditionnotincategory', 'tool_dynamic_cohorts', '', $condition->get_name());
        }
        $condition->set_scope($scope);

        return $condition;
    }

    /**
     * Extra validation.
     *
     * @param  array $data Data to validate.
     * @param  array $files Array of files.
     * @return array of additional errors, or overridden errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $condition = $this->get_condition();
        $errors = array_merge($errors, $condition->config_form_validate($data));
        if (empty($errors)) {
            // The scope check reads the config the form would store.
            $condition->set_config_data($condition::retrieve_config_data((object) $data));
            $errors = $condition->validate_scope();
        }

        return $errors;
    }
}

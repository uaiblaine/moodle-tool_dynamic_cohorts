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

use tool_dynamic_cohorts\condition_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Setting: the conditions a category-level user may use.
 *
 * The choices are the discovered conditions that scope their referents
 * (condition_base::supports_category_scope()); they are loaded only when the setting is shown or
 * its default is applied, not every time the admin tree is built.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Anderson Blaine
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_category_conditions extends \admin_setting_configmulticheckbox {
    /**
     * Constructor.
     *
     * @param string $name Setting name.
     * @param \lang_string $visiblename Setting title.
     * @param \lang_string $description Setting description.
     */
    public function __construct(string $name, \lang_string $visiblename, \lang_string $description) {
        parent::__construct($name, $visiblename, $description, null, null);
    }

    /**
     * Load the choices: every discovered condition that supports a category scope.
     *
     * @return bool
     */
    public function load_choices() {
        if (is_array($this->choices)) {
            return true;
        }

        $this->choices = [];
        foreach (condition_manager::get_all_conditions(false) as $classname => $condition) {
            if ($condition->supports_category_scope()) {
                $this->choices[$classname] = $condition->get_name();
            }
        }
        return true;
    }

    /**
     * The default: every condition this plugin ships (D3); conditions of other plugins are off.
     *
     * @return array
     */
    public function get_defaultsetting() {
        $default = [];
        foreach (condition_manager::get_all_conditions(false) as $classname => $condition) {
            if ($condition->supports_category_scope() && condition_manager::is_builtin($classname)) {
                $default[$classname] = 1;
            }
        }
        return $default;
    }
}

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

namespace tool_dynamic_cohorts\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;

/**
 * Privacy Subsystem.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements core_userlist_provider, metadata_provider, plugin_provider {
    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'tool_dynamic_cohorts',
            [
                'name' => 'privacy:metadata:tool_dynamic_cohorts:name',
                'usermodified' => 'privacy:metadata:tool_dynamic_cohorts:usermodified',
            ],
            'privacy:metadata:tool_dynamic_cohorts'
        );

        $collection->add_database_table(
            'tool_dynamic_cohorts_c',
            [
                'ruleid' => 'privacy:metadata:tool_dynamic_cohorts_c:ruleid',
                'usermodified' => 'privacy:metadata:tool_dynamic_cohorts_c:usermodified',
            ],
            'privacy:metadata:tool_dynamic_cohorts_c'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();

        if ($DB->record_exists('tool_dynamic_cohorts', ['usermodified' => $userid])) {
            $contextlist->add_system_context();
        }

        if ($DB->record_exists('tool_dynamic_cohorts_c', ['usermodified' => $userid])) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_system) {
            return;
        }

        $sql = "SELECT usermodified FROM {tool_dynamic_cohorts}";
        $userlist->add_from_sql('usermodified', $sql, []);

        $sql = "SELECT usermodified FROM {tool_dynamic_cohorts_c}";
        $userlist->add_from_sql('usermodified', $sql, []);
    }

    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        // Rules.
        $rules = [];
        $recordset = $DB->get_recordset('tool_dynamic_cohorts', ['usermodified' => $user->id], '', 'name');

        foreach ($recordset as $record) {
            $rules[] = [
                'rulename' => format_string($record->name),
            ];
        }
        $recordset->close();

        // Conditions.
        $conditions = [];
        $sql = 'SELECT c.*, r.name as rulename
                  FROM {tool_dynamic_cohorts_c} c
                  JOIN {tool_dynamic_cohorts} r ON (r.id = c.ruleid)
                 WHERE c.usermodified = :userid
              ORDER BY r.id ASC';

        $recordset = $DB->get_recordset_sql($sql, ['userid' => $user->id]);

        foreach ($recordset as $record) {
            $conditions[] = [
                'rulename' => format_string($record->rulename),
                'classname' => format_string($record->classname),
            ];
        }
        $recordset->close();

        /* One export_data() call carrying both collections, not one per collection.
           moodle_content_writer::export_data() resolves a subcontext to a single data.json
           and writes it with file_put_contents, so a second call on the SAME subcontext
           overwrites the first rather than merging: any user holding both a rule row and a
           condition row got an export containing only the conditions, with the rule names
           silently gone and no error anywhere. */
        if (count($rules) > 0 || count($conditions) > 0) {
            $context = \context_system::instance();
            $contextpath = [get_string('pluginname', 'tool_dynamic_cohorts')];

            writer::with_context($context)->export_data($contextpath, (object) [
                'rules' => $rules,
                'conditions' => $conditions,
            ]);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        $DB->set_field('tool_dynamic_cohorts', 'usermodified', 0);
        $DB->set_field('tool_dynamic_cohorts_c', 'usermodified', 0);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            $DB->set_field('tool_dynamic_cohorts', 'usermodified', 0, ['usermodified' => $userid]);
            $DB->set_field('tool_dynamic_cohorts_c', 'usermodified', 0, ['usermodified' => $userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        [$userinsql, $userinparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);

        $DB->set_field_select('tool_dynamic_cohorts', 'usermodified', 0, ' usermodified ' . $userinsql, $userinparams);
        $DB->set_field_select('tool_dynamic_cohorts_c', 'usermodified', 0, ' usermodified ' . $userinsql, $userinparams);
    }
}

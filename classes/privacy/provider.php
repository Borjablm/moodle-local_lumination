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
 * Privacy API provider for local_lumination.
 *
 * Declares all personal data stored in plugin database tables and sent to
 * the external Lumination API, and implements export/delete operations for
 * GDPR compliance.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lumination\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context_system;

/**
 * Privacy provider for the Lumination plugin.
 *
 * Declares that document metadata and API usage data are stored locally
 * with userid references, and that document content is sent to the external
 * Lumination API for text extraction and course generation.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe all personal data stored and transmitted by this plugin.
     *
     * @param collection $collection The collection of metadata items to add to.
     * @return collection The updated collection with this plugin's metadata.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_lumination_documents',
            [
                'userid' => 'privacy:metadata:documents:userid',
                'document_uuid' => 'privacy:metadata:documents:document_uuid',
                'filename' => 'privacy:metadata:documents:filename',
                'timecreated' => 'privacy:metadata:documents:timecreated',
            ],
            'privacy:metadata:documents'
        );

        $collection->add_database_table(
            'local_lumination_usage',
            [
                'userid' => 'privacy:metadata:usage:userid',
                'action' => 'privacy:metadata:usage:action',
                'tokens_in' => 'privacy:metadata:usage:tokens_in',
                'tokens_out' => 'privacy:metadata:usage:tokens_out',
                'credits' => 'privacy:metadata:usage:credits',
                'timecreated' => 'privacy:metadata:usage:timecreated',
            ],
            'privacy:metadata:usage'
        );

        $collection->add_external_location_link(
            'lumination_api',
            [
                'document_content' => 'privacy:metadata:api:document_content',
            ],
            'privacy:metadata:api'
        );

        return $collection;
    }

    /**
     * Get all contexts where the given user has data.
     *
     * This plugin operates at system level, so all user data is in CONTEXT_SYSTEM.
     *
     * @param int $userid The ID of the user.
     * @return contextlist Contexts containing the user's data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $params = ['userid' => $userid, 'contextlevel' => CONTEXT_SYSTEM];

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {local_lumination_documents} d ON d.userid = :userid
                 WHERE c.contextlevel = :contextlevel
                   AND c.instanceid = 0";
        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {local_lumination_usage} u ON u.userid = :userid
                 WHERE c.contextlevel = :contextlevel
                   AND c.instanceid = 0";
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get all user IDs that have data within a context.
     *
     * @param userlist $userlist The userlist to add users to.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!($context instanceof context_system)) {
            return;
        }

        $sql = "SELECT DISTINCT userid FROM {local_lumination_documents}";
        $userlist->add_from_sql('userid', $sql, []);

        $sql = "SELECT DISTINCT userid FROM {local_lumination_usage}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Export personal data for the given approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export data for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $context = context_system::instance();

        // Export documents.
        $documents = $DB->get_records('local_lumination_documents', ['userid' => $userid]);
        if (!empty($documents)) {
            $exportdata = [];
            foreach ($documents as $doc) {
                $exportdata[] = (object) [
                    'filename' => $doc->filename,
                    'document_uuid' => $doc->document_uuid,
                    'timecreated' => \core_privacy\local\request\transform::datetime($doc->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_lumination'), 'documents'],
                (object) ['documents' => $exportdata]
            );
        }

        // Export usage records.
        $usage = $DB->get_records('local_lumination_usage', ['userid' => $userid]);
        if (!empty($usage)) {
            $exportdata = [];
            foreach ($usage as $record) {
                $exportdata[] = (object) [
                    'action' => $record->action,
                    'tokens_in' => $record->tokens_in,
                    'tokens_out' => $record->tokens_out,
                    'credits' => $record->credits,
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_lumination'), 'usage'],
                (object) ['usage' => $exportdata]
            );
        }
    }

    /**
     * Delete all personal data for the given user in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_lumination_documents', ['userid' => $userid]);
        $DB->delete_records('local_lumination_usage', ['userid' => $userid]);
    }

    /**
     * Delete all personal data for all users in a context.
     *
     * @param \context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!($context instanceof context_system)) {
            return;
        }

        $DB->delete_records('local_lumination_documents');
        $DB->delete_records('local_lumination_usage');
    }

    /**
     * Delete all data for the specified users within a context.
     *
     * @param approved_userlist $userlist The approved users to delete data for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!($context instanceof context_system)) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_lumination_documents', "userid {$usersql}", $userparams);
        $DB->delete_records_select('local_lumination_usage', "userid {$usersql}", $userparams);
    }
}

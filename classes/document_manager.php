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
 * Document manager for uploading files to the Lumination API.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lumination;

/**
 * Tracks Lumination document UUIDs by Moodle context.
 *
 * The AI Tutor API v1 accepts documents inline (file_b64) rather than as
 * pre-registered uploads, so course generation no longer pre-uploads files.
 * This class retains lookup of any previously stored document UUIDs.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document_manager {
    /** @var api_client The Lumination API client instance. */
    private api_client $api;

    /**
     * Constructor.
     *
     * @param api_client|null $api An API client instance, or null to create a default one.
     */
    public function __construct(?api_client $api = null) {
        $this->api = $api ?? new api_client();
    }

    /**
     * Get all document UUIDs for a given Moodle context.
     *
     * @param int $contextid The Moodle context ID to look up.
     * @return array An array of document UUID strings.
     */
    public function get_document_uuids(int $contextid): array {
        global $DB;
        $records = $DB->get_records('local_lumination_documents', ['contextid' => $contextid]);
        return array_column($records, 'document_uuid');
    }
}

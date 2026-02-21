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

defined('MOODLE_INTERNAL') || die();

/**
 * Manages document uploads to the Lumination API and tracks document UUIDs.
 *
 * Provides methods to convert files to text via the API, upload files as
 * persistent documents, and retrieve document UUIDs by context.
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
     * Convert a Moodle stored_file to text using the material-to-text endpoint.
     *
     * This is the primary method for text extraction -- it extracts text from
     * PDFs, docs, etc. without needing the /process-material endpoint (which
     * requires a rate limiter).
     *
     * @param \stored_file $file The Moodle stored file to convert.
     * @return string The extracted text content.
     * @throws \moodle_exception If the API call fails or returns no text.
     */
    public function file_to_text(\stored_file $file): string {
        $content = $file->get_content();
        $b64content = base64_encode($content);
        $mimetype = $file->get_mimetype();
        $filename = $file->get_filename();

        $result = $this->api->post(
            '/api/material-to-text',
            [
                'content' => $b64content,
                'content_type' => $mimetype,
                'filename' => $filename,
            ]
        );

        if (empty($result['success']) || empty($result['text'])) {
            $error = $result['error'] ?? 'Unknown error';
            throw new \moodle_exception(
                'errorapifailed',
                'local_lumination',
                '',
                $error
            );
        }

        return $result['text'];
    }

    /**
     * Upload a Moodle stored_file to the Lumination API as a persistent document.
     *
     * Uses the /process-material endpoint to upload and process the file.
     * Stores the returned document UUID in the local_lumination_documents table.
     *
     * @param \stored_file $file The Moodle stored file to upload.
     * @param int $userid The ID of the user performing the upload.
     * @param int $contextid The Moodle context ID for tracking the document.
     * @return string The Lumination document UUID.
     * @throws \moodle_exception If the API call fails or no document UUID is returned.
     */
    public function upload_file(\stored_file $file, int $userid, int $contextid): string {
        global $DB;

        $content = $file->get_content();
        $b64content = base64_encode($content);
        $filename = $file->get_filename();
        $mimetype = $file->get_mimetype();

        $result = $this->api->post(
            '/lumination-ai/api/v1/process-material',
            [
                'items' => [
                    [
                        'content' => $b64content,
                        'content_type' => $mimetype,
                        'filename' => $filename,
                    ],
                ],
            ]
        );

        $documentuuid = $result['items'][0]['document_uuid']
            ?? $result['document_uuid']
            ?? $result['items'][0]['id']
            ?? null;

        if (empty($documentuuid)) {
            $resultkeys = implode(', ', array_keys($result));
            throw new \moodle_exception(
                'errorapifailed',
                'local_lumination',
                '',
                'No document_uuid in upload response. Keys: ' . $resultkeys
            );
        }

        $record = new \stdClass();
        $record->userid = $userid;
        $record->contextid = $contextid;
        $record->document_uuid = $documentuuid;
        $record->filename = $file->get_filename();
        $record->timecreated = time();
        $DB->insert_record('local_lumination_documents', $record);

        return $documentuuid;
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

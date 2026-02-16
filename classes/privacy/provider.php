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
 * This class describes the external data sent to the Lumination API service
 * so that Moodle's privacy subsystem can inform users about what personal
 * data may leave the platform during course generation workflows.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lumination\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy provider for the Lumination plugin.
 *
 * Declares that document content uploaded by users is sent to the external
 * Lumination API for text extraction and course outline generation. No user
 * data is stored locally by this plugin beyond the standard Moodle session.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider {

    /**
     * Describe the external data locations linked by this plugin.
     *
     * Document content uploaded by users is transmitted to the Lumination API
     * for text extraction (material-to-text) and AI-driven course outline
     * generation. This method registers that external link so Moodle can
     * include it in privacy data exports and deletion requests.
     *
     * @param collection $collection The collection of metadata items to add to.
     * @return collection The updated collection with this plugin's metadata.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'lumination_api',
            [
                'document_content' => 'privacy:metadata',
            ],
            'privacy:metadata'
        );
        return $collection;
    }
}

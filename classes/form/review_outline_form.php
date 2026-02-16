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
 * Review outline form for step 2 of course generation.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lumination\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Step 2 form: Review and edit the generated course outline before creating the Moodle course.
 *
 * Uses a hidden JSON field and an AMD JS module for dynamic add/remove of
 * modules and lessons in the outline editor.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_outline_form extends \moodleform {
    /**
     * Define the form elements for reviewing and editing the course outline.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $outline = $this->_customdata['outline'] ?? [];
        $guideuuid = $this->_customdata['guide_uuid'] ?? '';
        $title = $this->_customdata['title'] ?? '';
        $categoryid = $this->_customdata['categoryid'] ?? 1;
        $documentuuids = $this->_customdata['document_uuids'] ?? [];
        $language = $this->_customdata['language'] ?? 'en';

        // Hidden fields to carry state forward.
        $mform->addElement('hidden', 'guide_uuid', $guideuuid);
        $mform->setType('guide_uuid', PARAM_TEXT);

        $mform->addElement(
            'hidden',
            'document_uuids',
            implode(',', $documentuuids)
        );
        $mform->setType('document_uuids', PARAM_TEXT);

        $mform->addElement('hidden', 'language', $language);
        $mform->setType('language', PARAM_TEXT);

        $mform->addElement('hidden', 'categoryid', $categoryid);
        $mform->setType('categoryid', PARAM_INT);

        // Course title (editable).
        $mform->addElement('text', 'title', get_string('uploadtitle', 'local_lumination'));
        $mform->setType('title', PARAM_TEXT);
        $mform->setDefault('title', $title);

        // Hidden field carrying the full outline as JSON.
        $modules = $outline['modules'] ?? $outline['guide']['modules'] ?? [];
        $mform->addElement('hidden', 'outline_json', json_encode($modules));
        $mform->setType('outline_json', PARAM_RAW);

        // Container for the JS-driven outline editor.
        $mform->addElement('html', '<div id="lumination-outline-editor"></div>');

        $this->add_action_buttons(true, get_string('createcourse', 'local_lumination'));
    }

    /**
     * Extract the edited outline data from the submitted form.
     *
     * Decodes the JSON from the hidden outline_json field and returns
     * the modules array structure.
     *
     * @return array The modules with lessons structure, or an empty array if invalid.
     */
    public function get_edited_outline(): array {
        $data = $this->get_data();
        $json = $data->outline_json ?? '[]';
        $modules = json_decode($json, true);

        if (empty($modules) || !is_array($modules)) {
            return [];
        }

        return $modules;
    }
}

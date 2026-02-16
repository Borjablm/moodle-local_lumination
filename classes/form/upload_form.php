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
 * Upload form for step 1 of course generation.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lumination\form;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Step 1 form: Upload documents and configure course generation.
 *
 * Collects a course title, uploaded documents, optional instructions,
 * language preference, and target course category before generating
 * a course outline via the Lumination API.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_form extends \moodleform {
    /**
     * Define the form elements for document upload and course configuration.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        // Course title.
        $mform->addElement('text', 'title', get_string('uploadtitle', 'local_lumination'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('title', 'uploadtitle', 'local_lumination');

        // File upload.
        $fileoptions = [
            'maxbytes' => 50 * 1024 * 1024,
            'maxfiles' => 10,
            'accepted_types' => ['.pdf', '.doc', '.docx', '.txt', '.pptx', '.ppt'],
        ];
        $mform->addElement(
            'filemanager',
            'documents',
            get_string('uploadfiles', 'local_lumination'),
            null,
            $fileoptions
        );
        $mform->addRule('documents', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('documents', 'uploadfiles', 'local_lumination');

        // Instructions (optional).
        $mform->addElement(
            'textarea',
            'instructions',
            get_string('instructions', 'local_lumination'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('instructions', PARAM_TEXT);
        $mform->addHelpButton('instructions', 'instructions', 'local_lumination');

        // Language.
        $languages = [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'pt' => 'Portuguese',
            'it' => 'Italian',
            'nl' => 'Dutch',
            'default' => 'Auto-detect',
        ];
        $mform->addElement(
            'select',
            'language',
            get_string('language', 'local_lumination'),
            $languages
        );
        $mform->setDefault('language', 'en');

        // Course category.
        $categories = \core_course_category::make_categories_list();
        $mform->addElement(
            'select',
            'categoryid',
            get_string('category', 'local_lumination'),
            $categories
        );
        $mform->setDefault('categoryid', 1);

        $this->add_action_buttons(true, get_string('generateoutline', 'local_lumination'));
    }
}

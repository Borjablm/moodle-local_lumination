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
 * Course Generator - Step 1: Upload documents and generate outline.
 *
 * Flow: Upload files -> extract text via API -> generate outline via agent chat -> review.
 *
 * URL: /local/lumination/course_generator.php
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/lumination:generatecourse', $context);

$PAGE->set_url(new moodle_url('/local/lumination/course_generator.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('coursegenerator', 'local_lumination'));
$PAGE->set_heading(get_string('coursegenerator', 'local_lumination'));
$PAGE->set_pagelayout('standard');

// Check API is configured.
$api = new \local_lumination\api_client();
if (!$api->is_configured()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('errornoapi', 'local_lumination'), 'error');
    echo $OUTPUT->footer();
    die;
}

$form = new \local_lumination\form\upload_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/'));
}

if ($data = $form->get_data()) {
    $docmanager = new \local_lumination\document_manager($api);
    $generator = new \local_lumination\course_generator($api);

    // Step 1: Extract text from uploaded files.
    $fs = get_file_storage();
    $usercontext = context_user::instance($USER->id);
    $files = $fs->get_area_files(
        $usercontext->id,
        'user',
        'draft',
        $data->documents,
        '',
        false
    );

    $alltext = '';
    $errors = [];
    foreach ($files as $file) {
        if ($file->get_filename() === '.') {
            continue;
        }
        try {
            $text = $docmanager->file_to_text($file);
            $alltext .= "\n\n--- " . $file->get_filename() . " ---\n\n" . $text;
        } catch (\Exception $e) {
            $errors[] = $file->get_filename() . ': ' . $e->getMessage();
        }
    }

    if (empty($alltext)) {
        echo $OUTPUT->header();
        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo $OUTPUT->notification($error, 'error');
            }
        } else {
            echo $OUTPUT->notification(get_string('errornocontent', 'local_lumination'), 'error');
        }
        $form->display();
        echo $OUTPUT->footer();
        die;
    }

    // Step 2: Generate course outline using the agent chat.
    try {
        $outline = $generator->generate_outline_from_text(
            $alltext,
            $data->title,
            $data->instructions ?? '',
            $data->language ?? 'en'
        );

        // Store in session cache for the review page.
        $cache = cache::make('local_lumination', 'outline');
        $cache->set('data', [
            'outline' => $outline,
            'source_text' => $alltext,
            'title' => $data->title,
            'categoryid' => $data->categoryid,
            'language' => $data->language,
        ]);

        redirect(new moodle_url('/local/lumination/review_outline.php'));
    } catch (\Exception $e) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(
            get_string('errorapifailed', 'local_lumination', $e->getMessage()),
            'error'
        );
        $form->display();
        echo $OUTPUT->footer();
        die;
    }
}

// Display the upload form.
echo $OUTPUT->header();
echo html_writer::tag('p', get_string('coursegenerator_desc', 'local_lumination'));
$form->display();
echo $OUTPUT->footer();

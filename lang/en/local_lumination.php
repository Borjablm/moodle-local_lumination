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
 * English language strings for the Lumination AI plugin.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_create_course'] = 'Create course';
$string['action_generate_lesson'] = 'Generate lesson';
$string['action_generate_outline'] = 'Generate outline';
$string['action_upload_document'] = 'Upload document';
$string['addlesson'] = 'Add lesson';
$string['addmodule'] = 'Add module';
$string['category'] = 'Course category';
$string['coursecreated'] = 'Course created successfully!';
$string['coursecreated_desc'] = 'Your new course has been created with {$a->sections} sections and {$a->activities} activities.';
$string['coursegenerator'] = 'AI Course Generator';
$string['coursegenerator_desc'] = 'Upload documents and generate a full Moodle course structure using Lumination AI.';
$string['coursegenerator_nav'] = 'AI Course Generator';
$string['createcourse'] = 'Create Moodle course';
$string['creatingcourse'] = 'Creating your Moodle course...';
$string['errorapifailed'] = 'Lumination API request failed: {$a}';
$string['errornoapi'] = 'Lumination API is not configured. Please set the API Base URL and API Key in plugin settings.';
$string['errornocontent'] = 'No content was returned from the API.';
$string['generateoutline'] = 'Generate outline';
$string['generatingcourse'] = 'Generating your course...';
$string['generatingcourse_desc'] = 'AI is writing content for each lesson. This may take a few minutes '
    . '-- please do not close this page.';
$string['generatingoutline'] = 'Generating course outline from your documents...';
$string['instructions'] = 'Instructions';
$string['instructions_help'] = 'Optional guidance for course generation (e.g. audience level, tone, scope).';
$string['language'] = 'Language';
$string['lessonname'] = 'Lesson';
$string['lumination:generatecourse'] = 'Generate courses using Lumination AI';
$string['lumination:manage'] = 'Manage Lumination AI settings';
$string['lumination:viewusage'] = 'View Lumination AI usage statistics';
$string['modulename'] = 'Module';
$string['outlinereview'] = 'Review course outline';
$string['outlinereview_desc'] = 'Review and edit the generated outline before creating the course.';
$string['pluginname'] = 'Lumination AI';
$string['privacy:metadata:api'] = 'Document content uploaded by users is sent to the Lumination API for text extraction and AI-driven course generation.';
$string['privacy:metadata:api:document_content'] = 'The content of documents uploaded by the user, sent for text extraction and course outline generation.';
$string['privacy:metadata:documents'] = 'Records of documents uploaded to the Lumination API for course generation.';
$string['privacy:metadata:documents:document_uuid'] = 'The unique identifier assigned to the document by the Lumination API.';
$string['privacy:metadata:documents:filename'] = 'The original filename of the uploaded document.';
$string['privacy:metadata:documents:timecreated'] = 'The date and time the document was uploaded.';
$string['privacy:metadata:documents:userid'] = 'The ID of the user who uploaded the document.';
$string['privacy:metadata:usage'] = 'Records of API calls made to the Lumination API, tracking token and credit consumption.';
$string['privacy:metadata:usage:action'] = 'The type of API action performed (e.g. generate_outline, generate_lesson).';
$string['privacy:metadata:usage:credits'] = 'The number of credits charged for the API call.';
$string['privacy:metadata:usage:timecreated'] = 'The date and time the API call was made.';
$string['privacy:metadata:usage:tokens_in'] = 'The number of input tokens consumed by the API call.';
$string['privacy:metadata:usage:tokens_out'] = 'The number of output tokens consumed by the API call.';
$string['privacy:metadata:usage:userid'] = 'The ID of the user who triggered the API call.';
$string['removelesson'] = 'Remove lesson';
$string['removemodule'] = 'Remove module';
$string['setting_apibaseurl'] = 'API Base URL';
$string['setting_apibaseurl_desc'] = 'The base URL of your Lumination API instance (e.g. https://api.lumination.ai).';
$string['setting_apikey'] = 'API Key';
$string['setting_apikey_desc'] = 'Your Lumination API key. Found in your Lumination dashboard.';
$string['uploadfiles'] = 'Source documents';
$string['uploadfiles_help'] = 'Upload PDFs, Word docs, or text files that contain the course material.';
$string['uploadtitle'] = 'Course title';
$string['uploadtitle_help'] = 'A title for the course to be generated.';
$string['usage'] = 'API Usage';
$string['usage_action'] = 'Action';
$string['usage_by_action'] = 'Usage by action';
$string['usage_by_user'] = 'Top users';
$string['usage_credits'] = 'Credits';
$string['usage_daily'] = 'Daily breakdown';
$string['usage_date'] = 'Date';
$string['usage_days'] = '{$a} days';
$string['usage_desc'] = 'View Lumination AI API usage statistics including tokens consumed and credits charged.';
$string['usage_nav'] = 'API Usage Dashboard';
$string['usage_nodata'] = 'No usage data for this period.';
$string['usage_period'] = 'Period';
$string['usage_requests'] = 'Requests';
$string['usage_tokens_in'] = 'Tokens in';
$string['usage_tokens_out'] = 'Tokens out';
$string['usage_total'] = 'Total';
$string['usage_user'] = 'User';
$string['viewcourse'] = 'View course';

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
 * Course generator for the Lumination plugin.
 *
 * Orchestrates the full course generation workflow including outline extraction,
 * Lumination course creation, and Moodle course mapping.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lumination;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/course/lib.php');
require_once($GLOBALS['CFG']->dirroot . '/course/modlib.php');
require_once($GLOBALS['CFG']->dirroot . '/lib/resourcelib.php');

/**
 * Orchestrates the full course generation workflow.
 *
 * This class handles:
 * 1. Extracting a guide/outline from documents or text.
 * 2. Creating a Lumination course via the API.
 * 3. Mapping the Lumination course structure to a Moodle course with sections and page activities.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_generator {
    /** @var api_client The Lumination API client instance. */
    private api_client $api;

    /**
     * Constructor.
     *
     * @param api_client|null $api Optional API client instance for dependency injection.
     */
    public function __construct(?api_client $api = null) {
        $this->api = $api ?? new api_client();
    }

    /**
     * Generate a course outline from extracted text using the agent chat endpoint.
     *
     * Since /process-material may be unavailable, this uses /agent/chat to generate
     * a structured JSON outline from the text content directly. The text is truncated
     * to approximately 15000 characters to avoid token limits.
     *
     * @param string $text Extracted document text to generate an outline from.
     * @param string $title Course title hint for the AI prompt.
     * @param string $instructions Extra constraints such as audience, tone, or scope.
     * @param string $language Language code for the generated outline (e.g. 'en').
     * @return array Parsed outline with 'title' and 'modules' keys, where each module
     *               contains 'title', 'description', and 'lessons' entries.
     * @throws \moodle_exception If the API returns empty or unparseable content.
     */
    public function generate_outline_from_text(
        string $text,
        string $title = '',
        string $instructions = '',
        string $language = 'en'
    ): array {
        // Truncate text to avoid token limits (keep ~15000 chars).
        $maxchars = 15000;
        if (strlen($text) > $maxchars) {
            $text = substr($text, 0, $maxchars) . "\n\n[... content truncated for outline generation ...]";
        }

        $prompt = "You are a course design assistant. Based on the following document content, "
            . "create a structured course outline.\n\n"
            . "Course title: " . ($title ?: "Generate an appropriate title") . "\n"
            . ($instructions ? "Instructions: {$instructions}\n" : "")
            . "Language: {$language}\n\n"
            . "Use this markdown format exactly:\n\n"
            . "## Module 1: Title Here\n"
            . "Description of the module.\n"
            . "1. Lesson Title One\n"
            . "2. Lesson Title Two\n\n"
            . "## Module 2: Title Here\n"
            . "Description.\n"
            . "1. Lesson Title\n\n"
            . "Create 3-8 modules with 2-5 lessons each.\n\n"
            . "Document content:\n" . $text;

        $result = $this->api->post(
            '/lumination-ai/api/v1/agent/chat',
            [
                'persist' => false,
                'stream' => false,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]
        );

        usage_logger::log('generate_outline', $result);

        // Extract the response text (double-nested).
        $responsetext = $result['response']['response']
            ?? $result['response']
            ?? '';

        if (empty($responsetext) || !is_string($responsetext)) {
            throw new \moodle_exception('errornocontent', 'local_lumination');
        }

        // Try JSON first (in case the agent returns it).
        $json = $responsetext;
        $tick = chr(96);
        $codeblockpattern = '/' . $tick . '{3}(?:json)?\s*([\s\S]*?)' . $tick . '{3}/';
        if (preg_match($codeblockpattern, $json, $matches)) {
            $json = $matches[1];
        }
        $outline = json_decode(trim($json), true);
        if (!empty($outline['modules'])) {
            $outline['title'] = $outline['title'] ?? $title;
            return $outline;
        }

        // Parse markdown response into structured outline.
        return $this->parse_markdown_outline($responsetext, $title);
    }

    /**
     * Parse a markdown-formatted outline into a structured array.
     *
     * Handles formats like:
     *   ## Module 1: Title
     *   Description text
     *   1. Lesson title
     *   2. Lesson title
     *
     * @param string $text The markdown text to parse.
     * @param string $title The course title to include in the returned structure.
     * @return array Parsed outline with 'title' and 'modules' keys.
     * @throws \moodle_exception If no modules could be parsed from the text.
     */
    private function parse_markdown_outline(string $text, string $title): array {
        $modules = [];
        $currentmodule = null;

        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $line = trim($line);

            // Match module headers: "## Module 1: Title" or "## Title".
            if (preg_match('/^#{1,3}\s+(?:Module\s*\d*[:.]\s*)?(.+)/i', $line, $m)) {
                if ($currentmodule !== null) {
                    $modules[] = $currentmodule;
                }
                $currentmodule = [
                    'title' => trim($m[1]),
                    'description' => '',
                    'lessons' => [],
                ];
                continue;
            }

            if ($currentmodule === null) {
                continue;
            }

            // Match numbered lessons: "1. Lesson Title" or "- Lesson Title".
            if (
                preg_match('/^\d+[\.\)]\s+(.+)/', $line, $m) ||
                preg_match('/^[-*]\s+(.+)/', $line, $m)
            ) {
                $lessontitle = trim($m[1]);
                // Clean up: remove trailing descriptions after " - ".
                $lessontitle = preg_replace('/\s*[-\x{2013}]\s+.*$/u', '', $lessontitle);
                $currentmodule['lessons'][] = ['title' => $lessontitle];
                continue;
            }

            // Non-empty lines that are not headers or list items are description.
            if (!empty($line) && empty($currentmodule['lessons'])) {
                $currentmodule['description'] .= ($currentmodule['description'] ? ' ' : '') . $line;
            }
        }

        if ($currentmodule !== null) {
            $modules[] = $currentmodule;
        }

        if (empty($modules)) {
            throw new \moodle_exception(
                'errorapifailed',
                'local_lumination',
                '',
                'Could not parse course outline from AI response'
            );
        }

        return [
            'title' => $title,
            'modules' => $modules,
        ];
    }

    /**
     * Generate a course outline (guide) from uploaded documents via the guides:extract endpoint.
     *
     * Requires document_uuids obtained from the /process-material endpoint.
     * Use generate_outline_from_text() as a fallback when document UUIDs are not available.
     *
     * @param array $documentuuids Array of document UUIDs from the process-material endpoint.
     * @param string $title Optional course title hint to guide extraction.
     * @param string $instructions Optional extra constraints such as audience, tone, or scope.
     * @param string $language Language code for the generated outline (e.g. 'en').
     * @return array The API result containing the extracted guide data.
     * @throws \moodle_exception If the API does not return guide content.
     */
    public function generate_outline(
        array $documentuuids,
        string $title = '',
        string $instructions = '',
        string $language = 'en'
    ): array {
        $data = ['document_uuids' => $documentuuids];
        if (!empty($title)) {
            $data['title'] = $title;
        }
        if (!empty($instructions)) {
            $data['instructions'] = $instructions;
        }

        $result = $this->api->post('/lumination-ai/api/v1/features/courses/guides:extract', $data);

        usage_logger::log('generate_outline', $result);

        if (empty($result['guide'])) {
            throw new \moodle_exception('errornocontent', 'local_lumination');
        }

        return $result;
    }

    /**
     * Create a Lumination course from a guide or documents via the API.
     *
     * This is step 2 of the course generation workflow. It sends the course creation
     * request to the Lumination API and returns the resulting course data including
     * the course UUID needed for subsequent operations.
     *
     * @param string $title The course title.
     * @param string[] $documentuuids Source document UUIDs from the process-material endpoint.
     * @param string $guideuuid Optional guide UUID from the outline generation step.
     * @param string $language Language code for the course content (e.g. 'en').
     * @return array Course data from the API including 'course_uuid'.
     * @throws \moodle_exception If the API does not return a course_uuid.
     */
    public function create_lumination_course(
        string $title,
        array $documentuuids,
        string $guideuuid = '',
        string $language = 'en'
    ): array {
        $data = [
            'title' => $title,
            'generate_async' => false,
            'lang_code' => $language,
        ];

        if (!empty($guideuuid)) {
            $data['guide_uuid'] = $guideuuid;
        }
        if (!empty($documentuuids)) {
            $data['document_uuids'] = $documentuuids;
        }

        $result = $this->api->post('/lumination-ai/api/v1/features/courses', $data);

        usage_logger::log('create_course', $result);

        if (empty($result['course_uuid'])) {
            throw new \moodle_exception(
                'errorapifailed',
                'local_lumination',
                '',
                'No course_uuid returned'
            );
        }

        return $result;
    }

    /**
     * Fetch the full course structure from the Lumination API.
     *
     * Retrieves the complete course definition including all modules and their
     * lessons for the given Lumination course UUID.
     *
     * @param string $courseuuid The Lumination course UUID to fetch.
     * @return array Course structure containing modules and lessons.
     */
    public function get_course_structure(string $courseuuid): array {
        return $this->api->get("/lumination-ai/api/v1/features/courses/{$courseuuid}");
    }

    /**
     * Generate content for a single lesson via the Lumination API.
     *
     * Triggers content generation for a specific lesson within a Lumination course.
     * The API populates the lesson with educational content based on the source material.
     *
     * @param string $courseuuid The Lumination course UUID containing the lesson.
     * @param string $lessonuuid The UUID of the lesson to generate content for.
     * @return array Lesson data with generated content from the API.
     */
    public function generate_lesson_content(string $courseuuid, string $lessonuuid): array {
        $result = $this->api->post(
            "/lumination-ai/api/v1/features/courses/{$courseuuid}/lessons/{$lessonuuid}:generate"
        );
        usage_logger::log('generate_lesson', $result);
        return $result;
    }

    /**
     * Create a Moodle course directly from an outline and source text.
     *
     * This method bypasses the Lumination course UUID workflow and instead generates
     * lesson content on-the-fly using the agent chat endpoint. Each lesson in the
     * provided modules array becomes a mod_page activity in the corresponding
     * Moodle course section.
     *
     * @param array $modules Edited modules array from the review form, each containing
     *                       'title', 'description', and 'lessons' keys.
     * @param string $title The full name for the Moodle course.
     * @param int $categoryid Moodle course category ID to place the course in.
     * @param string $sourcetext Original document text used to ground lesson content generation.
     * @param string $language Language code for the generated content (e.g. 'en').
     * @return \stdClass The created Moodle course object with additional lumination_sections
     *                   and lumination_activities properties.
     */
    public function create_moodle_course_from_text(
        array $modules,
        string $title,
        int $categoryid,
        string $sourcetext = '',
        string $language = 'en'
    ): \stdClass {
        global $DB;

        // Ensure valid category -- fall back to first available.
        if (empty($categoryid) || !$DB->record_exists('course_categories', ['id' => $categoryid])) {
            $firstcat = $DB->get_record('course_categories', [], 'id', IGNORE_MULTIPLE);
            $categoryid = $firstcat ? $firstcat->id : 1;
        }

        $shortname = $this->generate_unique_shortname($title);

        $courseobj = new \stdClass();
        $courseobj->fullname = $title;
        $courseobj->shortname = $shortname;
        $courseobj->category = $categoryid;
        $courseobj->format = 'topics';
        $courseobj->numsections = count($modules);
        $courseobj->visible = 1;
        $courseobj->enablecompletion = 1;

        $course = create_course($courseobj);
        $activitycount = 0;

        // Truncate source text for lesson generation prompts.
        $contexttext = substr($sourcetext, 0, 10000);

        foreach ($modules as $i => $module) {
            $sectionnumber = $i + 1;
            $moduletitle = $module['title'] ?? 'Module ' . ($i + 1);

            $section = $DB->get_record(
                'course_sections',
                [
                    'course' => $course->id,
                    'section' => $sectionnumber,
                ]
            );
            if ($section) {
                course_update_section(
                    $course,
                    $section,
                    [
                        'name' => $moduletitle,
                        'summary' => $module['description'] ?? '',
                    ]
                );
            }

            $lessons = $module['lessons'] ?? [];
            foreach ($lessons as $lesson) {
                $lessontitle = $lesson['title'] ?? 'Lesson';

                // Generate lesson content via agent chat.
                $lessoncontent = $this->generate_lesson_content_from_text(
                    $lessontitle,
                    $moduletitle,
                    $contexttext,
                    $language
                );

                $this->add_page_activity($course, $sectionnumber, $lessontitle, $lessoncontent);
                $activitycount++;
            }
        }

        rebuild_course_cache($course->id, true);

        $course->lumination_sections = count($modules);
        $course->lumination_activities = $activitycount;

        return $course;
    }

    /**
     * Generate lesson content using the agent chat endpoint with source text as context.
     *
     * Constructs a prompt requesting HTML-formatted educational content for the given
     * lesson and sends it to the Lumination agent chat API. If the API call fails or
     * returns empty content, a placeholder HTML string is returned instead.
     *
     * @param string $lessontitle The title of the lesson to generate content for.
     * @param string $moduletitle The title of the parent module for context.
     * @param string $sourcetext Truncated source material to ground the content generation.
     * @param string $language Language code for the generated content (e.g. 'en').
     * @return string Generated HTML content for the lesson, or a placeholder on failure.
     */
    private function generate_lesson_content_from_text(
        string $lessontitle,
        string $moduletitle,
        string $sourcetext,
        string $language = 'en'
    ): string {
        $prompt = "You are a course content writer. Write educational content for a lesson.\n\n"
            . "Module: {$moduletitle}\n"
            . "Lesson: {$lessontitle}\n"
            . "Language: {$language}\n\n"
            . "Write the lesson content in HTML format. "
            . "Do NOT include the lesson title as a heading -- it is already displayed separately by the platform.\n"
            . "Start directly with the content. Include:\n"
            . "- Clear explanations based on the source material\n"
            . "- Key concepts highlighted with <strong> tags\n"
            . "- Organized with <h3> subheadings where appropriate\n"
            . "- 300-600 words\n\n"
            . "Base the content on this source material:\n" . $sourcetext;

        try {
            $result = $this->api->post(
                '/lumination-ai/api/v1/agent/chat',
                [
                    'persist' => false,
                    'stream' => false,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]
            );

            usage_logger::log('generate_lesson', $result);

            $content = $result['response']['response'] ?? $result['response'] ?? '';
            if (!empty($content) && is_string($content)) {
                // Strip leading markdown headings that duplicate the lesson title.
                $content = preg_replace('/^\s*#{1,4}\s+.*?\n+/', '', $content, 1);
                // Strip leading HTML headings that duplicate the lesson title.
                $content = preg_replace('/^\s*<h[1-4][^>]*>.*?<\/h[1-4]>\s*/i', '', $content, 1);
                return trim($content);
            }
        } catch (\Exception $e) {
            // Lesson generation failed -- fall through to placeholder content below.
            unset($e);
        }

        return '<p><em>Content for "' . htmlspecialchars($lessontitle)
            . '" will be added. Edit this page to complete.</em></p>';
    }

    /**
     * Create a Moodle course from the Lumination course structure.
     *
     * This is step 3 of the full course generation workflow. It fetches the complete
     * course structure from the Lumination API, then maps each Lumination module to a
     * Moodle course section and each lesson to a mod_page activity. Optionally accepts
     * edited modules from the review form to override the API response.
     *
     * @param string $courseuuid The Lumination course UUID to build the Moodle course from.
     * @param string $title The full name for the Moodle course.
     * @param int $categoryid Moodle course category ID to place the course in.
     * @param array|null $editedmodules Optional edited outline from the review form. When
     *                                  provided, these modules override the API response.
     * @return \stdClass The created Moodle course object with additional lumination_sections
     *                   and lumination_activities properties.
     */
    public function create_moodle_course(
        string $courseuuid,
        string $title,
        int $categoryid,
        ?array $editedmodules = null
    ): \stdClass {
        global $DB, $USER;

        // Fetch the full Lumination course structure.
        $lumcourse = $this->get_course_structure($courseuuid);
        $coursedata = $lumcourse['course'] ?? $lumcourse;

        // Use the edited modules if provided, otherwise use API response.
        $modules = $editedmodules ?? $coursedata['modules'] ?? [];

        // Generate a unique shortname.
        $shortname = $this->generate_unique_shortname($title);

        // Create the Moodle course.
        $courseobj = new \stdClass();
        $courseobj->fullname = $title;
        $courseobj->shortname = $shortname;
        $courseobj->category = $categoryid;
        $courseobj->format = 'topics';
        $courseobj->numsections = count($modules);
        $courseobj->visible = 1;
        $courseobj->enablecompletion = 1;

        $course = create_course($courseobj);

        $activitycount = 0;

        // Map each Lumination module to a Moodle section.
        foreach ($modules as $i => $module) {
            $sectionnumber = $i + 1;
            $moduletitle = $module['title'] ?? $module['name'] ?? 'Module ' . ($i + 1);
            $moduledesc = $module['description'] ?? '';

            // Update section name and summary.
            $section = $DB->get_record(
                'course_sections',
                [
                    'course' => $course->id,
                    'section' => $sectionnumber,
                ]
            );
            if ($section) {
                $section->name = $moduletitle;
                $section->summary = $moduledesc;
                $section->summaryformat = \FORMAT_HTML;
                course_update_section(
                    $course,
                    $section,
                    ['name' => $moduletitle, 'summary' => $moduledesc]
                );
            }

            // Map each lesson to a mod_page activity.
            $lessons = $module['lessons'] ?? $module['topics'] ?? [];
            foreach ($lessons as $lesson) {
                $lessontitle = $lesson['title'] ?? $lesson['name'] ?? 'Lesson';
                $lessonuuid = $lesson['lesson_uuid'] ?? $lesson['uuid'] ?? '';

                // Generate lesson content from Lumination (if we have a UUID).
                $lessoncontent = '';
                if (!empty($lessonuuid)) {
                    try {
                        $lessondata = $this->generate_lesson_content($courseuuid, $lessonuuid);
                        $lessoncontent = $lessondata['lesson']['content']
                            ?? $lessondata['content']
                            ?? $lessondata['response']
                            ?? '';
                    } catch (\Exception $e) {
                        // If lesson generation fails, use a placeholder.
                        $lessoncontent = '<p><em>Content generation pending. '
                            . 'Edit this page to add content.</em></p>';
                    }
                }

                if (empty($lessoncontent)) {
                    $lessoncontent = '<p><em>Content will be generated. '
                        . 'Edit this page to add or modify content.</em></p>';
                }

                // Create mod_page activity.
                $this->add_page_activity($course, $sectionnumber, $lessontitle, $lessoncontent);
                $activitycount++;
            }
        }

        rebuild_course_cache($course->id, true);

        // Store metadata about the generation for reference.
        $course->lumination_sections = count($modules);
        $course->lumination_activities = $activitycount;

        return $course;
    }

    /**
     * Add a Page activity (mod_page) to a course section.
     *
     * Creates a new mod_page course module in the specified section with the given
     * name and HTML content. The page is configured with open display mode and
     * heading printing enabled.
     *
     * @param \stdClass $course The Moodle course object to add the activity to.
     * @param int $sectionnumber The section number within the course (1-based).
     * @param string $name The display name for the page activity.
     * @param string $content The HTML content for the page body.
     * @return void
     */
    private function add_page_activity(
        \stdClass $course,
        int $sectionnumber,
        string $name,
        string $content
    ): void {
        global $DB;

        $moduleid = $DB->get_field('modules', 'id', ['name' => 'page']);

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = 'page';
        $moduleinfo->module = $moduleid;
        $moduleinfo->course = $course->id;
        $moduleinfo->section = $sectionnumber;
        $moduleinfo->name = $name;
        $moduleinfo->intro = '';
        $moduleinfo->introformat = \FORMAT_HTML;
        $moduleinfo->content = $content;
        $moduleinfo->contentformat = \FORMAT_HTML;
        $moduleinfo->display = \RESOURCELIB_DISPLAY_OPEN;
        $moduleinfo->printintro = 0;
        $moduleinfo->printheading = 1;
        $moduleinfo->printlastmodified = 0;
        $moduleinfo->visible = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->timemodified = time();

        // Required for add_moduleinfo but we handle defaults.
        $moduleinfo->cmidnumber = '';
        $moduleinfo->groupmode = 0;
        $moduleinfo->groupingid = 0;
        $moduleinfo->availability = null;
        $moduleinfo->completion = 0;

        add_moduleinfo($moduleinfo, $course);
    }

    /**
     * Generate a unique short name for a Moodle course.
     *
     * Creates a shortname by converting the title to uppercase alphanumeric characters
     * (max 15 chars), then appends an incrementing counter suffix if the shortname
     * already exists in the database.
     *
     * @param string $title The course title to derive the shortname from.
     * @return string A unique shortname that does not conflict with existing courses.
     */
    private function generate_unique_shortname(string $title): string {
        global $DB;

        // Create base shortname from title.
        $base = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $title), 0, 15));
        if (empty($base)) {
            $base = 'LUM';
        }

        $shortname = $base;
        $counter = 1;
        while ($DB->record_exists('course', ['shortname' => $shortname])) {
            $shortname = $base . '-' . $counter;
            $counter++;
        }

        return $shortname;
    }
}

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
 * Unit tests for the course_generator class.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @category   test
 */

namespace local_lumination;

/**
 * Tests for {@see \local_lumination\course_generator}.
 *
 * @covers \local_lumination\course_generator
 */
final class course_generator_test extends \advanced_testcase {
    /** @var \ReflectionMethod Cached reflection for parse_markdown_outline. */
    private \ReflectionMethod $parsemethod;

    /** @var \ReflectionMethod Cached reflection for generate_unique_shortname. */
    private \ReflectionMethod $shortnamemethod;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Make private methods accessible via reflection.
        $this->parsemethod = new \ReflectionMethod(course_generator::class, 'parse_markdown_outline');
        $this->parsemethod->setAccessible(true);

        $this->shortnamemethod = new \ReflectionMethod(course_generator::class, 'generate_unique_shortname');
        $this->shortnamemethod->setAccessible(true);
    }

    /**
     * Test that a well-formed markdown outline is parsed into modules and lessons.
     */
    public function test_parse_markdown_outline_basic(): void {
        $markdown = <<<'MD'
## Module 1: Introduction to PHP
A brief overview of the PHP language.
1. History of PHP
2. Installing PHP

## Module 2: Variables and Types
Learn about data types and variables.
1. Scalar Types
2. Arrays and Objects
3. Type Juggling
MD;

        $generator = new course_generator($this->create_mock_api());
        $result = $this->parsemethod->invoke($generator, $markdown, 'My PHP Course');

        $this->assertSame('My PHP Course', $result['title']);
        $this->assertCount(2, $result['modules']);

        // First module.
        $mod1 = $result['modules'][0];
        $this->assertSame('Introduction to PHP', $mod1['title']);
        $this->assertStringContainsString('brief overview', $mod1['description']);
        $this->assertCount(2, $mod1['lessons']);
        $this->assertSame('History of PHP', $mod1['lessons'][0]['title']);
        $this->assertSame('Installing PHP', $mod1['lessons'][1]['title']);

        // Second module.
        $mod2 = $result['modules'][1];
        $this->assertSame('Variables and Types', $mod2['title']);
        $this->assertCount(3, $mod2['lessons']);
        $this->assertSame('Scalar Types', $mod2['lessons'][0]['title']);
        $this->assertSame('Arrays and Objects', $mod2['lessons'][1]['title']);
        $this->assertSame('Type Juggling', $mod2['lessons'][2]['title']);
    }

    /**
     * Test parsing with bullet-point lessons (- or *) instead of numbered lists.
     */
    public function test_parse_markdown_outline_bullet_lessons(): void {
        $markdown = <<<'MD'
## Getting Started
Overview of the topic.
- Setting Up Your Environment
- Your First Program

## Advanced Topics
Going deeper.
* Concurrency
* Error Handling
MD;

        $generator = new course_generator($this->create_mock_api());
        $result = $this->parsemethod->invoke($generator, $markdown, 'Bullet Course');

        $this->assertCount(2, $result['modules']);
        $this->assertSame('Setting Up Your Environment', $result['modules'][0]['lessons'][0]['title']);
        $this->assertSame('Your First Program', $result['modules'][0]['lessons'][1]['title']);
        $this->assertSame('Concurrency', $result['modules'][1]['lessons'][0]['title']);
        $this->assertSame('Error Handling', $result['modules'][1]['lessons'][1]['title']);
    }

    /**
     * Test that a markdown outline with no module headers throws an exception.
     */
    public function test_parse_markdown_outline_empty_throws(): void {
        $generator = new course_generator($this->create_mock_api());

        $this->expectException(\moodle_exception::class);
        $this->parsemethod->invoke($generator, 'No modules here, just plain text.', 'Empty');
    }

    /**
     * Test that lesson titles with trailing description text after " - " are cleaned.
     */
    public function test_parse_markdown_outline_strips_lesson_descriptions(): void {
        $markdown = <<<'MD'
## Module 1: Basics
Intro.
1. Variables - Learn about storing data
2. Functions - Reusable code blocks
MD;

        $generator = new course_generator($this->create_mock_api());
        $result = $this->parsemethod->invoke($generator, $markdown, 'Strip Test');

        $this->assertSame('Variables', $result['modules'][0]['lessons'][0]['title']);
        $this->assertSame('Functions', $result['modules'][0]['lessons'][1]['title']);
    }

    /**
     * Test that a normal title produces an uppercase alphanumeric shortname.
     */
    public function test_generate_unique_shortname_normal_title(): void {
        $generator = new course_generator($this->create_mock_api());
        $shortname = $this->shortnamemethod->invoke($generator, 'Introduction to Python');

        // Expected: upper-case, non-alnum stripped, max 15 chars.
        $this->assertSame('INTRODUCTIONTOP', $shortname);
    }

    /**
     * Test that an empty title falls back to 'LUM'.
     */
    public function test_generate_unique_shortname_empty_title(): void {
        $generator = new course_generator($this->create_mock_api());
        $shortname = $this->shortnamemethod->invoke($generator, '');

        $this->assertSame('LUM', $shortname);
    }

    /**
     * Test that a title with only special characters falls back to 'LUM'.
     */
    public function test_generate_unique_shortname_special_chars_only(): void {
        $generator = new course_generator($this->create_mock_api());
        $shortname = $this->shortnamemethod->invoke($generator, '!@#$%^&*()');

        $this->assertSame('LUM', $shortname);
    }

    /**
     * Test that duplicate shortnames get an incrementing suffix.
     */
    public function test_generate_unique_shortname_increments_on_duplicate(): void {
        global $DB;

        $generator = new course_generator($this->create_mock_api());

        // Create a course that will collide with the generated shortname.
        $this->getDataGenerator()->create_course([
            'fullname' => 'Existing',
            'shortname' => 'TESTCOURSE',
        ]);

        $shortname = $this->shortnamemethod->invoke($generator, 'Test Course');
        $this->assertSame('TESTCOURSE-1', $shortname);

        // Create the -1 variant too, so the next one should be -2.
        $this->getDataGenerator()->create_course([
            'fullname' => 'Existing 2',
            'shortname' => 'TESTCOURSE-1',
        ]);

        $shortname2 = $this->shortnamemethod->invoke($generator, 'Test Course');
        $this->assertSame('TESTCOURSE-2', $shortname2);
    }

    /**
     * Test that create_moodle_course_from_text creates a Moodle course with the
     * correct structure: sections named after modules, page activities per lesson.
     */
    public function test_create_moodle_course_from_text(): void {
        global $DB;

        $this->setAdminUser();

        // Build a mock api_client that returns fake lesson content.
        $mockapi = $this->createMock(api_client::class);
        $mockapi->method('post')->willReturn([
            'response' => [
                'response' => '<h3>Generated Lesson</h3><p>This is AI-generated content.</p>',
            ],
        ]);

        $generator = new course_generator($mockapi);

        $modules = [
            [
                'title' => 'Getting Started',
                'description' => 'The basics of the subject.',
                'lessons' => [
                    ['title' => 'Welcome'],
                    ['title' => 'Setup Guide'],
                ],
            ],
            [
                'title' => 'Core Concepts',
                'description' => 'Dive into the core material.',
                'lessons' => [
                    ['title' => 'Fundamentals'],
                    ['title' => 'Best Practices'],
                    ['title' => 'Common Pitfalls'],
                ],
            ],
        ];

        $category = $this->getDataGenerator()->create_category();
        $course = $generator->create_moodle_course_from_text(
            $modules,
            'Test AI Course',
            $category->id,
            'Some source document text for context.',
            'en'
        );

        // Verify the course was created with the right name.
        $this->assertSame('Test AI Course', $course->fullname);
        $this->assertTrue($DB->record_exists('course', ['id' => $course->id]));

        // Verify section count: 2 modules means sections 1 and 2 (section 0 is General).
        $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
        // Moodle creates section 0 plus our numsections.
        $this->assertGreaterThanOrEqual(3, count($sections));

        // Check section names.
        $sectionarray = array_values($sections);
        $this->assertSame('Getting Started', $sectionarray[1]->name);
        $this->assertSame('Core Concepts', $sectionarray[2]->name);

        // Verify page activities were created: 2 + 3 = 5 total.
        $pages = $DB->get_records('page', ['course' => $course->id]);
        $this->assertCount(5, $pages);

        // Verify activity names.
        $pagenames = array_column($pages, 'name');
        $this->assertContains('Welcome', $pagenames);
        $this->assertContains('Setup Guide', $pagenames);
        $this->assertContains('Fundamentals', $pagenames);
        $this->assertContains('Best Practices', $pagenames);
        $this->assertContains('Common Pitfalls', $pagenames);

        // Verify each page got the mocked content.
        foreach ($pages as $page) {
            $this->assertStringContainsString('Generated Lesson', $page->content);
        }

        // Verify the metadata attached to the returned course object.
        $this->assertSame(2, $course->lumination_sections);
        $this->assertSame(5, $course->lumination_activities);
    }

    /**
     * Test that create_moodle_course_from_text falls back to placeholder content
     * when the API call fails.
     */
    public function test_create_moodle_course_from_text_api_failure_uses_placeholder(): void {
        global $DB;

        $this->setAdminUser();

        // Mock that throws on every post() call.
        $mockapi = $this->createMock(api_client::class);
        $mockapi->method('post')->willThrowException(
            new \moodle_exception('errorapifailed', 'local_lumination', '', 'Simulated failure')
        );

        $generator = new course_generator($mockapi);

        $modules = [
            [
                'title' => 'Solo Module',
                'description' => '',
                'lessons' => [
                    ['title' => 'Only Lesson'],
                ],
            ],
        ];

        $category = $this->getDataGenerator()->create_category();
        $course = $generator->create_moodle_course_from_text(
            $modules,
            'Fallback Course',
            $category->id,
            'Source text.',
            'en'
        );

        // The course should still be created.
        $this->assertSame('Fallback Course', $course->fullname);

        // The page content should contain the placeholder text.
        $pages = $DB->get_records('page', ['course' => $course->id]);
        $this->assertCount(1, $pages);
        $page = reset($pages);
        $this->assertStringContainsString('will be added', $page->content);
    }

    /**
     * Create a minimal mock api_client (not expected to be called).
     *
     * @return api_client
     */
    private function create_mock_api(): api_client {
        $mock = $this->createMock(api_client::class);
        return $mock;
    }
}

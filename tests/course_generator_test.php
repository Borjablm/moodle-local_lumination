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
    /** @var \ReflectionMethod Cached reflection for guide_to_outline. */
    private \ReflectionMethod $guidemethod;

    /** @var \ReflectionMethod Cached reflection for generate_unique_shortname. */
    private \ReflectionMethod $shortnamemethod;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Make private methods accessible via reflection.
        $this->guidemethod = new \ReflectionMethod(course_generator::class, 'guide_to_outline');
        $this->guidemethod->setAccessible(true);

        $this->shortnamemethod = new \ReflectionMethod(course_generator::class, 'generate_unique_shortname');
        $this->shortnamemethod->setAccessible(true);
    }

    /**
     * Test that an AI Tutor guide is normalised into the plugin's outline shape.
     */
    public function test_guide_to_outline_maps_structure(): void {
        $generator = new course_generator($this->create_mock_api());

        $guide = [
            'course_title' => 'Biology 101',
            'course_structure' => [
                ['module_name' => 'Cells', 'lesson_names' => ['Membranes', 'The Nucleus']],
                ['module_name' => 'Energy', 'lesson_names' => ['ATP']],
            ],
        ];

        $outline = $this->guidemethod->invoke($generator, $guide, 'Fallback Title');

        $this->assertSame('Biology 101', $outline['title']);
        $this->assertCount(2, $outline['modules']);
        $this->assertSame('Cells', $outline['modules'][0]['title']);
        $this->assertCount(2, $outline['modules'][0]['lessons']);
        $this->assertSame('Membranes', $outline['modules'][0]['lessons'][0]['title']);
        $this->assertSame('The Nucleus', $outline['modules'][0]['lessons'][1]['title']);
        $this->assertSame('Energy', $outline['modules'][1]['title']);
        $this->assertSame('ATP', $outline['modules'][1]['lessons'][0]['title']);
    }

    /**
     * Test that the fallback title is used when the guide has no course_title.
     */
    public function test_guide_to_outline_uses_fallback_title(): void {
        $generator = new course_generator($this->create_mock_api());

        $outline = $this->guidemethod->invoke($generator, ['course_structure' => []], 'My Fallback');

        $this->assertSame('My Fallback', $outline['title']);
        $this->assertSame([], $outline['modules']);
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
     * Test that create_moodle_course_from_outline creates a Moodle course with the
     * correct structure: sections named after modules, page activities per lesson.
     */
    public function test_create_moodle_course_from_outline(): void {
        global $DB;

        $this->setAdminUser();

        // Build a mock api_client whose /tutor job returns fake lesson content.
        $mockapi = $this->createMock(api_client::class);
        $mockapi->method('run')->willReturn([
            'result' => [
                'reply' => '<p>This is AI-generated content.</p>',
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
        $course = $generator->create_moodle_course_from_outline(
            $modules,
            'Test AI Course',
            $category->id,
            ['Understand the basics', 'Apply best practices'],
            'en'
        );

        // Verify the course was created with the right name.
        $this->assertSame('Test AI Course', $course->fullname);
        $this->assertTrue($DB->record_exists('course', ['id' => $course->id]));

        // Verify section count: 2 modules means sections 1 and 2 (section 0 is General).
        $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
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
            $this->assertStringContainsString('AI-generated content', $page->content);
        }

        // Verify the metadata attached to the returned course object.
        $this->assertSame(2, $course->lumination_sections);
        $this->assertSame(5, $course->lumination_activities);
    }

    /**
     * Test that create_moodle_course_from_outline falls back to placeholder content
     * when the lesson-content API call fails.
     */
    public function test_create_moodle_course_from_outline_api_failure_uses_placeholder(): void {
        global $DB;

        $this->setAdminUser();

        // Mock whose /tutor job throws on every call.
        $mockapi = $this->createMock(api_client::class);
        $mockapi->method('run')->willThrowException(
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
        $course = $generator->create_moodle_course_from_outline(
            $modules,
            'Fallback Course',
            $category->id,
            [],
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

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
 * Unit tests for the usage_logger class.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @category   test
 */

namespace local_lumination;

/**
 * Tests for {@see \local_lumination\usage_logger}.
 *
 * @covers \local_lumination\usage_logger
 */
final class usage_logger_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test that log() inserts a record with correct values.
     */
    public function test_log_inserts_record(): void {
        global $DB;

        $this->setAdminUser();

        $apiresponse = [
            'token_count_input' => 150,
            'token_count_output' => 300,
            'credits_charged' => 0.0025,
            'response' => ['response' => 'Some content'],
        ];

        usage_logger::log('generate_lesson', $apiresponse, 42);

        $records = $DB->get_records('local_lumination_usage');
        $this->assertCount(1, $records);

        $record = reset($records);
        $this->assertSame('generate_lesson', $record->action);
        $this->assertEquals(150, $record->tokens_in);
        $this->assertEquals(300, $record->tokens_out);
        $this->assertEquals(0.0025, (float) $record->credits);
        $this->assertEquals(42, $record->courseid);
    }

    /**
     * Test that log() handles missing usage fields gracefully (defaults to zero).
     */
    public function test_log_handles_missing_fields(): void {
        global $DB;

        $this->setAdminUser();

        usage_logger::log('generate_outline', ['response' => 'text']);

        $records = $DB->get_records('local_lumination_usage');
        $this->assertCount(1, $records);

        $record = reset($records);
        $this->assertEquals(0, $record->tokens_in);
        $this->assertEquals(0, $record->tokens_out);
        $this->assertEquals(0, (float) $record->credits);
        $this->assertNull($record->courseid);
    }

    /**
     * Test that get_summary() returns correct aggregated totals.
     */
    public function test_get_summary(): void {
        $this->setAdminUser();

        usage_logger::log('generate_outline', [
            'token_count_input' => 100,
            'token_count_output' => 200,
            'credits_charged' => 0.5,
        ]);
        usage_logger::log('generate_lesson', [
            'token_count_input' => 50,
            'token_count_output' => 150,
            'credits_charged' => 0.3,
        ]);

        $summary = usage_logger::get_summary(30);

        $this->assertEquals(2, $summary->total_requests);
        $this->assertEquals(150, $summary->total_tokens_in);
        $this->assertEquals(350, $summary->total_tokens_out);
        $this->assertEquals(0.8, (float) $summary->total_credits);
    }

    /**
     * Test that get_by_action() groups records correctly.
     */
    public function test_get_by_action(): void {
        $this->setAdminUser();

        usage_logger::log('generate_lesson', ['token_count_input' => 10, 'token_count_output' => 20, 'credits_charged' => 0.1]);
        usage_logger::log('generate_lesson', ['token_count_input' => 15, 'token_count_output' => 25, 'credits_charged' => 0.2]);
        usage_logger::log('generate_outline', ['token_count_input' => 100, 'token_count_output' => 200, 'credits_charged' => 1.0]);

        $byaction = usage_logger::get_by_action(30);

        // Should have 2 action groups.
        $this->assertCount(2, $byaction);

        // Map by action name for easy assertion.
        $map = [];
        foreach ($byaction as $row) {
            $map[$row->action] = $row;
        }

        $this->assertEquals(2, $map['generate_lesson']->requests);
        $this->assertEquals(25, $map['generate_lesson']->tokens_in);
        $this->assertEquals(1, $map['generate_outline']->requests);
    }

    /**
     * Test that get_by_user() returns user info with usage.
     */
    public function test_get_by_user(): void {
        global $USER;

        $this->setAdminUser();

        usage_logger::log('generate_lesson', ['credits_charged' => 0.5]);

        $byuser = usage_logger::get_by_user(30, 10);
        $this->assertCount(1, $byuser);
        $this->assertEquals($USER->id, $byuser[0]->userid);
        $this->assertEquals(1, $byuser[0]->requests);
    }
}

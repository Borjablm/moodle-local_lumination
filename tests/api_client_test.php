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
 * Unit tests for the api_client class.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @category   test
 */

namespace local_lumination;

/**
 * Tests for {@see \local_lumination\api_client}.
 *
 * @covers \local_lumination\api_client
 */
final class api_client_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test that is_configured() returns false when no API key is set.
     */
    public function test_is_configured_returns_false_when_empty(): void {
        unset_config('apikey', 'local_lumination');

        $client = new api_client();
        $this->assertFalse($client->is_configured());
    }

    /**
     * Test that is_configured() returns true when the API key is present.
     */
    public function test_is_configured_returns_true_when_set(): void {
        set_config('apikey', 'test-key-abc123', 'local_lumination');

        $client = new api_client();
        $this->assertTrue($client->is_configured());
    }
}

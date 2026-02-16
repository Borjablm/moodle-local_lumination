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
 * Usage logger for tracking Lumination API consumption.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lumination;

/**
 * Logs API usage (tokens, credits) to the local_lumination_usage table.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage_logger {

    /**
     * Log an API call's usage metrics.
     *
     * Extracts token_count_input, token_count_output, and credits_charged
     * from the API response and inserts a record into local_lumination_usage.
     *
     * @param string $action The action that triggered the API call (e.g. 'generate_outline').
     * @param array $apiresponse The decoded JSON response from the Lumination API.
     * @param int|null $courseid Optional Moodle course ID associated with the call.
     * @return void
     */
    public static function log(string $action, array $apiresponse, ?int $courseid = null): void {
        global $DB, $USER;

        $record = new \stdClass();
        $record->userid = $USER->id ?? 0;
        $record->courseid = $courseid;
        $record->action = substr($action, 0, 100);
        $record->tokens_in = (int) ($apiresponse['token_count_input'] ?? 0);
        $record->tokens_out = (int) ($apiresponse['token_count_output'] ?? 0);
        $record->credits = (float) ($apiresponse['credits_charged'] ?? 0);
        $record->timecreated = time();

        try {
            $DB->insert_record('local_lumination_usage', $record);
        } catch (\Exception $e) {
            // Usage logging should never break the main workflow.
            debugging('Lumination usage logging failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Get aggregated usage stats for a given period.
     *
     * @param int $days Number of days to look back.
     * @return \stdClass Object with total_requests, total_tokens_in, total_tokens_out, total_credits.
     */
    public static function get_summary(int $days = 30): \stdClass {
        global $DB;

        $since = time() - ($days * DAYSECS);
        $sql = "SELECT COUNT(*) AS total_requests,
                       COALESCE(SUM(tokens_in), 0) AS total_tokens_in,
                       COALESCE(SUM(tokens_out), 0) AS total_tokens_out,
                       COALESCE(SUM(credits), 0) AS total_credits
                  FROM {local_lumination_usage}
                 WHERE timecreated >= :since";

        return $DB->get_record_sql($sql, ['since' => $since]);
    }

    /**
     * Get daily usage breakdown for a given period.
     *
     * @param int $days Number of days to look back.
     * @return array Array of objects with day, requests, tokens_in, tokens_out, credits.
     */
    public static function get_daily_breakdown(int $days = 30): array {
        global $DB;

        $since = time() - ($days * DAYSECS);
        $sql = "SELECT FROM_UNIXTIME(timecreated, '%Y-%m-%d') AS day,
                       COUNT(*) AS requests,
                       SUM(tokens_in) AS tokens_in,
                       SUM(tokens_out) AS tokens_out,
                       SUM(credits) AS credits
                  FROM {local_lumination_usage}
                 WHERE timecreated >= :since
              GROUP BY FROM_UNIXTIME(timecreated, '%Y-%m-%d')
              ORDER BY day DESC";

        return array_values($DB->get_records_sql($sql, ['since' => $since]));
    }

    /**
     * Get usage breakdown by action type for a given period.
     *
     * @param int $days Number of days to look back.
     * @return array Array of objects with action, requests, tokens_in, tokens_out, credits.
     */
    public static function get_by_action(int $days = 30): array {
        global $DB;

        $since = time() - ($days * DAYSECS);
        $sql = "SELECT action,
                       COUNT(*) AS requests,
                       SUM(tokens_in) AS tokens_in,
                       SUM(tokens_out) AS tokens_out,
                       SUM(credits) AS credits
                  FROM {local_lumination_usage}
                 WHERE timecreated >= :since
              GROUP BY action
              ORDER BY requests DESC";

        return array_values($DB->get_records_sql($sql, ['since' => $since]));
    }

    /**
     * Get usage breakdown by user for a given period (top N users).
     *
     * @param int $days Number of days to look back.
     * @param int $limit Maximum number of users to return.
     * @return array Array of objects with userid, firstname, lastname, requests, credits.
     */
    public static function get_by_user(int $days = 30, int $limit = 10): array {
        global $DB;

        $since = time() - ($days * DAYSECS);
        $sql = "SELECT u.userid, u.requests, u.tokens_in, u.tokens_out, u.credits,
                       usr.firstname, usr.lastname
                  FROM (
                       SELECT userid,
                              COUNT(*) AS requests,
                              SUM(tokens_in) AS tokens_in,
                              SUM(tokens_out) AS tokens_out,
                              SUM(credits) AS credits
                         FROM {local_lumination_usage}
                        WHERE timecreated >= :since
                     GROUP BY userid
                     ORDER BY credits DESC
                  ) u
                  JOIN {user} usr ON usr.id = u.userid
              ORDER BY u.credits DESC";

        return array_values($DB->get_records_sql($sql, ['since' => $since], 0, $limit));
    }
}

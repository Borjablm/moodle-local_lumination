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
 * Central HTTP client for all Lumination API calls.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lumination;

/**
 * Central HTTP client for all Lumination API calls.
 *
 * Reads base_url and api_key from plugin settings. Uses Moodle's curl class
 * to make authenticated requests to the Lumination API.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_client {
    /** @var string Default AI Tutor API base URL (staging). Includes the /api/v1 prefix. */
    private const DEFAULT_BASE_URL = 'https://stage.ai-tutor.ai/api/v1';

    /** @var int Seconds between polls when waiting for an async job. */
    private const POLL_INTERVAL = 3;

    /** @var int Maximum seconds to wait for an async job to complete. */
    private const POLL_MAX_WAIT = 600;

    /** @var string The API key for authentication. */
    private string $apikey;

    /** @var int Request timeout in seconds. */
    private int $timeout;

    /**
     * Constructor.
     *
     * @param string|null $apikey The API key, or null to read from plugin settings.
     * @param int $timeout Request timeout in seconds.
     */
    public function __construct(?string $apikey = null, int $timeout = 120) {
        $this->apikey = $apikey ?? get_config('local_lumination', 'apikey');
        $this->timeout = $timeout;
    }

    /**
     * Resolve the API base URL from settings, falling back to the staging default.
     *
     * The base URL includes the /api/v1 prefix; endpoints are short paths like '/tutor'.
     *
     * @return string Base URL with no trailing slash.
     */
    private function base_url(): string {
        $configured = get_config('local_lumination', 'baseurl');
        return rtrim(!empty($configured) ? $configured : self::DEFAULT_BASE_URL, '/');
    }

    /**
     * Check if the API is configured with an API key.
     *
     * @return bool True if the API key is set.
     */
    public function is_configured(): bool {
        return !empty($this->apikey);
    }

    /**
     * Make a POST request with a JSON body.
     *
     * @param string $path API path starting with '/' (e.g. /course/guide).
     * @param array $data Request body data to be JSON-encoded.
     * @return array Decoded JSON response.
     * @throws \moodle_exception If the request fails or returns an error status.
     */
    public function post(string $path, array $data = []): array {
        $url = $this->base_url() . $path;
        $curl = new \curl();
        $this->set_common_options($curl);
        $curl->setHeader('Content-Type: application/json');

        $response = $curl->post($url, json_encode($data));
        return $this->handle_response($curl, $response, $url);
    }

    /**
     * Make a PUT request with a JSON body.
     *
     * @param string $path API path starting with '/' (e.g. /course/guide/{uuid}).
     * @param array $data Request body data to be JSON-encoded.
     * @return array Decoded JSON response.
     * @throws \moodle_exception If the request fails or returns an error status.
     */
    public function put(string $path, array $data = []): array {
        $url = $this->base_url() . $path;
        $curl = new \curl();
        $this->set_common_options($curl);
        $curl->setHeader('Content-Type: application/json');
        $curl->setopt(['CURLOPT_CUSTOMREQUEST' => 'PUT']);

        $response = $curl->post($url, json_encode($data));
        return $this->handle_response($curl, $response, $url);
    }

    /**
     * Make a GET request.
     *
     * @param string $path API path starting with '/' (e.g. /requests/{id}).
     * @param array $params Query parameters to append to the URL.
     * @return array Decoded JSON response.
     * @throws \moodle_exception If the request fails or returns an error status.
     */
    public function get(string $path, array $params = []): array {
        $url = $this->base_url() . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        $curl = new \curl();
        $this->set_common_options($curl);

        $response = $curl->get($url);
        return $this->handle_response($curl, $response, $url);
    }

    /**
     * Submit an asynchronous job and block until it completes.
     *
     * The AI Tutor API is asynchronous: a POST to a tool endpoint returns a
     * request_id, and the result is fetched by polling GET /requests/{id} until
     * status is 'completed' or 'failed'. This returns the completed job envelope
     * (with 'result', 'conversation_id', 'input_tokens', etc.).
     *
     * @param string $path Tool path (e.g. /tutor, /homework, /course).
     * @param array $data Request body.
     * @return array The completed job envelope.
     * @throws \moodle_exception If submission fails, the job fails, or it times out.
     */
    public function run(string $path, array $data = []): array {
        $submit = $this->post($path, $data);

        // Some responses may already be terminal.
        $status = $submit['status'] ?? '';
        if ($status === 'completed' || $status === 'failed') {
            if ($status === 'failed') {
                throw new \moodle_exception('errorapifailed', 'local_lumination', '', $submit['error'] ?? 'Job failed');
            }
            return $submit;
        }

        $requestid = $submit['request_id'] ?? '';
        if (empty($requestid)) {
            // No async id: return what we got.
            return $submit;
        }

        $deadline = time() + self::POLL_MAX_WAIT;
        while (time() < $deadline) {
            sleep(self::POLL_INTERVAL);
            $job = $this->get('/requests/' . rawurlencode($requestid));
            $status = $job['status'] ?? '';
            if ($status === 'completed') {
                return $job;
            }
            if ($status === 'failed') {
                throw new \moodle_exception(
                    'errorapifailed',
                    'local_lumination',
                    '',
                    $job['error'] ?? 'Job failed'
                );
            }
        }

        throw new \moodle_exception('errorapifailed', 'local_lumination', '', 'The request timed out.');
    }

    /**
     * Set common curl options including authentication headers and timeout.
     *
     * @param \curl $curl The Moodle curl instance to configure.
     * @return void
     */
    private function set_common_options(\curl $curl): void {
        $curl->setHeader('x-api-key: ' . $this->apikey);
        $curl->setHeader('X-REQUEST-ID: moodle-' . uniqid());
        $curl->setopt([
            'CURLOPT_TIMEOUT' => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ]);
    }

    /**
     * Handle the API response: check HTTP status, decode JSON, and validate.
     *
     * @param \curl $curl The curl instance used for the request.
     * @param string $response Raw response body.
     * @param string $url The requested URL (used in error messages).
     * @return array Decoded JSON response data.
     * @throws \moodle_exception If the connection failed, HTTP status is not 2xx, or JSON is invalid.
     */
    private function handle_response(\curl $curl, string $response, string $url): array {
        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if ($curl->get_errno()) {
            throw new \moodle_exception(
                'errorapifailed',
                'local_lumination',
                '',
                'Connection error: ' . $curl->error
            );
        }

        if ($httpcode < 200 || $httpcode >= 300) {
            $errormsg = "HTTP {$httpcode}";
            $decoded = json_decode($response, true);
            if (!empty($decoded['error'])) {
                $errormsg .= ': ' . $decoded['error'];
            } else if (!empty($decoded['detail'])) {
                $errormsg .= ': ' . $decoded['detail'];
            }
            throw new \moodle_exception(
                'errorapifailed',
                'local_lumination',
                '',
                $errormsg
            );
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new \moodle_exception(
                'errorapifailed',
                'local_lumination',
                '',
                'Invalid JSON response'
            );
        }

        return $decoded;
    }
}

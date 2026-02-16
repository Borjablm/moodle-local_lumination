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
    /** @var string The base URL for the Lumination API. */
    private string $baseurl;

    /** @var string The API key for authentication. */
    private string $apikey;

    /** @var int Request timeout in seconds. */
    private int $timeout;

    /**
     * Constructor.
     *
     * @param string|null $baseurl The API base URL, or null to read from plugin settings.
     * @param string|null $apikey The API key, or null to read from plugin settings.
     * @param int $timeout Request timeout in seconds.
     */
    public function __construct(?string $baseurl = null, ?string $apikey = null, int $timeout = 120) {
        $configurl = $baseurl ?? get_config('local_lumination', 'apibaseurl');
        $this->baseurl = rtrim($configurl ?: 'https://ai-sv-production.lumination.ai', '/');
        $this->apikey = $apikey ?? get_config('local_lumination', 'apikey');
        $this->timeout = $timeout;
    }

    /**
     * Check if the API is configured with a base URL and API key.
     *
     * @return bool True if both base URL and API key are set.
     */
    public function is_configured(): bool {
        return !empty($this->baseurl) && !empty($this->apikey);
    }

    /**
     * Make a POST request with a JSON body.
     *
     * @param string $path API path (e.g. /lumination-ai/api/v1/features/courses).
     * @param array $data Request body data to be JSON-encoded.
     * @return array Decoded JSON response.
     * @throws \moodle_exception If the request fails or returns an error status.
     */
    public function post(string $path, array $data = []): array {
        $url = $this->baseurl . $path;
        $curl = new \curl();
        $this->set_common_options($curl);
        $curl->setHeader('Content-Type: application/json');

        $response = $curl->post($url, json_encode($data));
        return $this->handle_response($curl, $response, $url);
    }

    /**
     * Make a GET request.
     *
     * @param string $path API path (e.g. /lumination-ai/api/v1/features/courses).
     * @param array $params Query parameters to append to the URL.
     * @return array Decoded JSON response.
     * @throws \moodle_exception If the request fails or returns an error status.
     */
    public function get(string $path, array $params = []): array {
        $url = $this->baseurl . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        $curl = new \curl();
        $this->set_common_options($curl);

        $response = $curl->get($url);
        return $this->handle_response($curl, $response, $url);
    }

    /**
     * Make a multipart POST request for file uploads.
     *
     * @param string $path API path (e.g. /lumination-ai/api/v1/process-material).
     * @param string $filepath Absolute path to the file on disk.
     * @param array $fields Additional form fields to include in the request.
     * @return array Decoded JSON response.
     * @throws \moodle_exception If the request fails or returns an error status.
     */
    public function post_multipart(string $path, string $filepath, array $fields = []): array {
        $url = $this->baseurl . $path;
        $curl = new \curl();
        $this->set_common_options($curl);
        // Don't set Content-Type -- curl sets multipart boundary automatically.

        $fields['file'] = new \CURLFile($filepath);
        $response = $curl->post($url, $fields);
        return $this->handle_response($curl, $response, $url);
    }

    /**
     * Set common curl options including authentication headers and timeout.
     *
     * @param \curl $curl The Moodle curl instance to configure.
     * @return void
     */
    private function set_common_options(\curl $curl): void {
        $curl->setHeader('X-API-KEY: ' . $this->apikey);
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

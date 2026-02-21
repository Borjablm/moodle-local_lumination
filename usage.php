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
 * API usage dashboard for the Lumination plugin.
 *
 * Displays token consumption and credit usage statistics for admins.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/lumination:viewusage', $context);

$days = optional_param('days', 30, PARAM_INT);
$validdays = [7, 14, 30, 60, 90];
if (!in_array($days, $validdays)) {
    $days = 30;
}

$PAGE->set_url(new moodle_url('/local/lumination/usage.php', ['days' => $days]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('usage', 'local_lumination'));
$PAGE->set_heading(get_string('usage', 'local_lumination'));
$PAGE->set_pagelayout('admin');

$summary = \local_lumination\usage_logger::get_summary($days);
$daily = \local_lumination\usage_logger::get_daily_breakdown($days);
$byaction = \local_lumination\usage_logger::get_by_action($days);
$byuser = \local_lumination\usage_logger::get_by_user($days);

// Build template data.
$templatedata = [
    'description' => get_string('usage_desc', 'local_lumination'),
    'periodlabel' => get_string('usage_period', 'local_lumination'),
    'periodlinks' => [],
    'cards' => [
        [
            'label' => get_string('usage_requests', 'local_lumination'),
            'value' => (int) $summary->total_requests,
        ],
        [
            'label' => get_string('usage_tokens_in', 'local_lumination'),
            'value' => number_format((int) $summary->total_tokens_in),
        ],
        [
            'label' => get_string('usage_tokens_out', 'local_lumination'),
            'value' => number_format((int) $summary->total_tokens_out),
        ],
        [
            'label' => get_string('usage_credits', 'local_lumination'),
            'value' => number_format((float) $summary->total_credits, 4),
        ],
    ],
    'hasdata' => !empty($daily) || !empty($byaction),
    'nodatamessage' => get_string('usage_nodata', 'local_lumination'),
    'hasdaily' => !empty($daily),
    'dailyheading' => get_string('usage_daily', 'local_lumination'),
    'dailyheaders' => [],
    'dailyrows' => [],
    'hasbyaction' => !empty($byaction),
    'byactionheading' => get_string('usage_by_action', 'local_lumination'),
    'actionheaders' => [],
    'actionrows' => [],
    'hasbyuser' => !empty($byuser),
    'byuserheading' => get_string('usage_by_user', 'local_lumination'),
    'userheaders' => [],
    'userrows' => [],
];

// Period filter links.
foreach ($validdays as $d) {
    $templatedata['periodlinks'][] = [
        'url' => (new moodle_url('/local/lumination/usage.php', ['days' => $d]))->out(false),
        'label' => get_string('usage_days', 'local_lumination', $d),
        'active' => ($d === $days),
    ];
}

// Common stat columns used in all tables.
$commonheaders = [
    ['label' => get_string('usage_requests', 'local_lumination')],
    ['label' => get_string('usage_tokens_in', 'local_lumination')],
    ['label' => get_string('usage_tokens_out', 'local_lumination')],
    ['label' => get_string('usage_credits', 'local_lumination')],
];

$templatedata['dailyheaders'] = array_merge(
    [['label' => get_string('usage_date', 'local_lumination')]],
    $commonheaders
);
$templatedata['actionheaders'] = array_merge(
    [['label' => get_string('usage_action', 'local_lumination')]],
    $commonheaders
);
$templatedata['userheaders'] = array_merge(
    [['label' => get_string('usage_user', 'local_lumination')]],
    $commonheaders
);

// Daily breakdown rows.
foreach ($daily as $row) {
    $templatedata['dailyrows'][] = [
        'cells' => [
            ['value' => $row->day],
            ['value' => (int) $row->requests],
            ['value' => number_format((int) $row->tokens_in)],
            ['value' => number_format((int) $row->tokens_out)],
            ['value' => number_format((float) $row->credits, 4)],
        ],
    ];
}

// By-action rows.
foreach ($byaction as $row) {
    $actionkey = 'action_' . $row->action;
    $actionlabel = get_string_manager()->string_exists($actionkey, 'local_lumination')
        ? get_string($actionkey, 'local_lumination')
        : $row->action;
    $templatedata['actionrows'][] = [
        'cells' => [
            ['value' => $actionlabel],
            ['value' => (int) $row->requests],
            ['value' => number_format((int) $row->tokens_in)],
            ['value' => number_format((int) $row->tokens_out)],
            ['value' => number_format((float) $row->credits, 4)],
        ],
    ];
}

// By-user rows.
foreach ($byuser as $row) {
    $templatedata['userrows'][] = [
        'cells' => [
            ['value' => fullname($row)],
            ['value' => (int) $row->requests],
            ['value' => number_format((int) $row->tokens_in)],
            ['value' => number_format((int) $row->tokens_out)],
            ['value' => number_format((float) $row->credits, 4)],
        ],
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_lumination/usage', $templatedata);
echo $OUTPUT->footer();

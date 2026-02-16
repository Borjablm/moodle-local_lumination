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

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('usage_desc', 'local_lumination'));

// Period filter.
echo '<div class="mb-3">';
echo '<strong>' . get_string('usage_period', 'local_lumination') . ':</strong> ';
foreach ($validdays as $d) {
    $url = new moodle_url('/local/lumination/usage.php', ['days' => $d]);
    $class = ($d === $days) ? 'btn btn-primary btn-sm mx-1' : 'btn btn-outline-secondary btn-sm mx-1';
    echo html_writer::link($url, get_string('usage_days', 'local_lumination', $d), ['class' => $class]);
}
echo '</div>';

// Summary cards.
echo '<div class="row mb-4">';

$cards = [
    ['label' => get_string('usage_requests', 'local_lumination'), 'value' => (int) $summary->total_requests],
    ['label' => get_string('usage_tokens_in', 'local_lumination'), 'value' => number_format((int) $summary->total_tokens_in)],
    ['label' => get_string('usage_tokens_out', 'local_lumination'), 'value' => number_format((int) $summary->total_tokens_out)],
    ['label' => get_string('usage_credits', 'local_lumination'), 'value' => number_format((float) $summary->total_credits, 4)],
];

foreach ($cards as $card) {
    echo '<div class="col-sm-6 col-md-3 mb-2">';
    echo '<div class="card">';
    echo '<div class="card-body text-center">';
    echo '<h5 class="card-title text-muted">' . $card['label'] . '</h5>';
    echo '<p class="card-text h3">' . $card['value'] . '</p>';
    echo '</div></div></div>';
}
echo '</div>';

if (empty($daily) && empty($byaction)) {
    echo $OUTPUT->notification(get_string('usage_nodata', 'local_lumination'), 'info');
    echo $OUTPUT->footer();
    die;
}

// Daily breakdown table.
if (!empty($daily)) {
    echo '<h4 class="mt-4">' . get_string('usage_daily', 'local_lumination') . '</h4>';
    $table = new html_table();
    $table->head = [
        get_string('usage_date', 'local_lumination'),
        get_string('usage_requests', 'local_lumination'),
        get_string('usage_tokens_in', 'local_lumination'),
        get_string('usage_tokens_out', 'local_lumination'),
        get_string('usage_credits', 'local_lumination'),
    ];
    $table->attributes['class'] = 'table table-striped table-sm';
    foreach ($daily as $row) {
        $table->data[] = [
            $row->day,
            (int) $row->requests,
            number_format((int) $row->tokens_in),
            number_format((int) $row->tokens_out),
            number_format((float) $row->credits, 4),
        ];
    }
    echo html_writer::table($table);
}

// Usage by action table.
if (!empty($byaction)) {
    echo '<h4 class="mt-4">' . get_string('usage_by_action', 'local_lumination') . '</h4>';
    $table = new html_table();
    $table->head = [
        get_string('usage_action', 'local_lumination'),
        get_string('usage_requests', 'local_lumination'),
        get_string('usage_tokens_in', 'local_lumination'),
        get_string('usage_tokens_out', 'local_lumination'),
        get_string('usage_credits', 'local_lumination'),
    ];
    $table->attributes['class'] = 'table table-striped table-sm';
    foreach ($byaction as $row) {
        $actionkey = 'action_' . $row->action;
        $actionlabel = get_string_manager()->string_exists($actionkey, 'local_lumination')
            ? get_string($actionkey, 'local_lumination')
            : $row->action;
        $table->data[] = [
            $actionlabel,
            (int) $row->requests,
            number_format((int) $row->tokens_in),
            number_format((int) $row->tokens_out),
            number_format((float) $row->credits, 4),
        ];
    }
    echo html_writer::table($table);
}

// Usage by user table.
if (!empty($byuser)) {
    echo '<h4 class="mt-4">' . get_string('usage_by_user', 'local_lumination') . '</h4>';
    $table = new html_table();
    $table->head = [
        get_string('usage_user', 'local_lumination'),
        get_string('usage_requests', 'local_lumination'),
        get_string('usage_tokens_in', 'local_lumination'),
        get_string('usage_tokens_out', 'local_lumination'),
        get_string('usage_credits', 'local_lumination'),
    ];
    $table->attributes['class'] = 'table table-striped table-sm';
    foreach ($byuser as $row) {
        $table->data[] = [
            fullname($row),
            (int) $row->requests,
            number_format((int) $row->tokens_in),
            number_format((int) $row->tokens_out),
            number_format((float) $row->credits, 4),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();

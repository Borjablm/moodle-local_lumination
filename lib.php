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
 * Library functions for the Lumination AI plugin.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add the AI Course Generator link to Moodle's main navigation drawer.
 *
 * Shows for any user with the generatecourse capability in the system context.
 *
 * @param global_navigation $navigation The global navigation object.
 * @return void
 */
function local_lumination_extend_navigation(global_navigation $navigation) {
    if (has_capability('local/lumination:generatecourse', context_system::instance())) {
        $node = $navigation->add(
            get_string('coursegenerator_nav', 'local_lumination'),
            new moodle_url('/local/lumination/course_generator.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'lumination_coursegen',
            new pix_icon('i/course', '')
        );
        $node->showinflatnavigation = true;
    }
}

/**
 * Add an "AI Course Generator" link alongside the native course management area.
 *
 * This shows up when viewing categories and courses in Site Administration.
 *
 * @param navigation_node $navigation The navigation node for the category settings.
 * @param context $context The context in which the capability is checked.
 * @return void
 */
function local_lumination_extend_navigation_category_settings(
    navigation_node $navigation,
    context $context
) {
    if (has_capability('local/lumination:generatecourse', $context)) {
        $navigation->add(
            get_string('coursegenerator_nav', 'local_lumination'),
            new moodle_url('/local/lumination/course_generator.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'lumination_coursegen',
            new pix_icon('i/course', '')
        );
    }
}

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
 * Admin settings for the Lumination AI plugin.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create a settings category for Lumination.
    $ADMIN->add(
        'localplugins',
        new admin_category(
            'local_lumination_cat',
            get_string('pluginname', 'local_lumination')
        )
    );

    // Settings page.
    $settings = new admin_settingpage(
        'local_lumination',
        get_string('pluginname', 'local_lumination')
    );

    $settings->add(
        new admin_setting_configtext(
            'local_lumination/apibaseurl',
            get_string('setting_apibaseurl', 'local_lumination'),
            get_string('setting_apibaseurl_desc', 'local_lumination'),
            '',
            PARAM_URL
        )
    );

    $settings->add(
        new admin_setting_configpasswordunmask(
            'local_lumination/apikey',
            get_string('setting_apikey', 'local_lumination'),
            get_string('setting_apikey_desc', 'local_lumination'),
            ''
        )
    );

    $ADMIN->add('local_lumination_cat', $settings);

    // Direct link to the Course Generator tool.
    $ADMIN->add(
        'local_lumination_cat',
        new admin_externalpage(
            'local_lumination_coursegen',
            get_string('coursegenerator_nav', 'local_lumination'),
            new moodle_url('/local/lumination/course_generator.php'),
            'local/lumination:generatecourse'
        )
    );

    // Direct link to the Usage Dashboard.
    $ADMIN->add(
        'local_lumination_cat',
        new admin_externalpage(
            'local_lumination_usage',
            get_string('usage_nav', 'local_lumination'),
            new moodle_url('/local/lumination/usage.php'),
            'local/lumination:viewusage'
        )
    );
}

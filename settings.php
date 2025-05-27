<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die;

$pluginname = 'local_course_explorer_service';

if ($hassiteconfig) {
    $settings = new theme_boost_admin_settingspage_tabs(
        $pluginname,
        get_string('pluginname', $pluginname)
    );

    $setting = new admin_settingpage(
        $pluginname,
        "Wordpress-Moodle Schnittstelle"
    );

    $setting->add(new admin_setting_configtext(
        $pluginname . '_fallback_img_url',
        "Fallbackbild",
        "Fallbackbild-URL für Absender und Empfehler",
        "https://mintcampus.org/wp-content/uploads/2023/09/MC_ICO_Ersteller.png"
    ));

    $settings->add($setting);
    $ADMIN->add('localplugins', $settings);
}

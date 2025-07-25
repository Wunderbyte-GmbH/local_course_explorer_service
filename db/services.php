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

defined('MOODLE_INTERNAL') || die();
$functions = [
    'local_course_explorer_service_get_course_list' => [
        'classname' => 'courses_provider',
        'methodname' => 'get_course_list',
        'classpath' => 'local/course_explorer_service/courses_provider.php',
        'description' => 'returns course info displayed in the course cards',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_course_explorer_service_get_my_courses' => [
        'classname' => 'my_courses_provider',
        'methodname' => 'get_my_courses',
        'classpath' => 'local/course_explorer_service/my_courses_provider.php',
        'description' => 'returns my courses',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_course_explorer_service_get_my_course_ids' => [
        'classname' => 'my_courses_provider',
        'methodname' => 'get_my_course_ids',
        'classpath' => 'local/course_explorer_service/my_courses_provider.php',
        'description' => 'returns ids of my courses',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_course_explorer_service_export_courses' => [
        'classname' => 'courses_exporter',
        'methodname' => 'export_courses',
        'classpath' => 'local/course_explorer_service/courses_exporter.php',
        'description' => 'returns all course info',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_course_explorer_service_set_favourite' => [
        'classname' => 'course_favourite_handler',
        'methodname' => 'set_favourite',
        'classpath' => 'local/course_explorer_service/course_favourite_handler.php',
        'description' => 'set favourite status of a course to true/false ',
        'type' => 'read',
        'ajax' => true,
    ],
];

$services = [
    'Course Explorer Services' => [
        'functions' => [
            'local_course_explorer_service_get_course_list',
            'local_course_explorer_service_get_my_courses',
            'local_course_explorer_service_get_my_course_ids',
            'local_course_explorer_service_export_courses',
            'local_course_explorer_service_set_favourite',
        ],
        'shortname' => 'course_explorer_service',
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];

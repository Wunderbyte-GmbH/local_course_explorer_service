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


require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/courses_exporter.php');

$title = "Install";
$PAGE->set_title($title);
$PAGE->set_heading($title);
if (!is_siteadmin()) {
    die();
}

try {
    // Call the export_courses method.
    $tagrecords  = courses_exporter::export_courses(0);
    echo "Courses exported successfully!";
} catch (Exception $e) {
    $tagrecords = "Error exporting courses: " . $e->getMessage();
}

echo $OUTPUT->header();
echo "<div id='rk_manager'>test</div>";
echo "<pre>" . var_dump($tagrecords) ."</pre>";
echo $OUTPUT->footer();

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

require_once($CFG->dirroot . '/course/lib.php');

class course_repository {
    public function get_course_metadata($courseid) {
        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        $data = $handler->get_instance_data($courseid, true);
        $metadata = [];
        foreach ($data as $item) {
            if (get_class($item) === "customfield_multiselect\data_controller") {
                if (strlen($item->get_value()) > 0) {
                    $metadata[$item->get_field()->get('shortname')] = explode(',', $item->get_value());
                } else {
                    $metadata[$item->get_field()->get('shortname')] = [];
                }
            } else if (get_class($item) === "customfield_picture\data_controller") {
                $exportdata = $item->export_value();
                if ($exportdata) {
                    if (preg_match('/<img[^>]+src="([^"]+)"/', $exportdata, $matches)) {
                        $metadata[$item->get_field()->get('shortname')] = $matches[1];
                    } else {
                        $metadata[$item->get_field()->get('shortname')] = null;
                    }
                }
            } else {
                $metadata[$item->get_field()->get('shortname')] = $item->get_value();
            }
        }
        return $metadata;
    }

    /**
     * Function is currently not used, but could be adjusted for further use cases
     * by only adjusting the map
     * @param $courseid
     * @param $filedname
     * @param $fielddata
     * @return void
     * @throws dml_exception
     */
    public function set_course_metadata($courseid, $filedname, $fielddata, $format = 0) {
        global $DB;

        $map = [
            'mc_moodle_partner_name' => 'charvalue',
            'mc_moodle_partner_name2' => 'charvalue',
            'mc_moodle_partner_name3' => 'charvalue',
            'mc_moodle_partner_name4' => 'charvalue',
        ];

        $subsql = '';
        if (isset($map[$filedname]) && $map[$filedname]) {
            $subsql = ", data.{$map[$filedname]} = :fielddata2";
            if ($format) {
                $subsql .= ", data.format = :format";
            }
        }

        $sql = "UPDATE {customfield_data} data
                JOIN {customfield_field} field ON data.fieldid = field.id and field.shortname = :fieldname
                SET data.value = :fielddata1{$subsql}
                where data.instanceid = :courseid";
        $params = [
            'fieldname' => $filedname,
            'fielddata1' => $fielddata,
            'courseid' => $courseid,
        ];
        if (isset($map[$filedname]) && $map[$filedname]) {
            $params['fielddata2'] = $fielddata;
            if ($format) {
                $params['format'] = $format;
            }
        }

        $DB->execute($sql, $params);
    }

    /**
     * @throws moodle_exception
     * @throws dml_exception
     */
    public function set_course_custom_field($courseid, $fieldname, $value) {
        global $DB;
        $course = (array) $DB->get_record('course', ['id' => $courseid]);
        $course[$fieldname] = $value;
        update_course((object) $course);
    }

    /**
     * @throws dml_exception
     */
    public function get_field_controller($fieldname, $fildtype): \core_customfield\field_controller {
        global $DB;
        $classname = "\\customfield_{$fildtype}\\field_controller";
        if (class_exists($classname)) {
            $methodname = 'create';
            $customfieldid = $DB->get_field('customfield_field', 'id', ['shortname' => $fieldname], MUST_EXIST);
            if (method_exists($classname, $methodname)) {
                return call_user_func_array([$classname, $methodname], [$customfieldid]);
            } else {
                throw new Exception("Method '$methodname' does not exist in class '$classname'.");
            }
        } else {
            throw new Exception("Class '$classname' does not exist.");
        }
    }
}

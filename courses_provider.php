<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

require_once(__DIR__ . '/classes/course_repository.php');

class courses_provider extends external_api
{
    public static function get_course_list_parameters()
    {
        return new external_function_parameters([
            'categoryids' => new external_value(
                PARAM_TEXT,
                'Ids of top categories, whose courses (sub-categories) are displayed'
            ),
            'userid' => new external_value(
                PARAM_INT,
                'Id of a user viewing the course block'
            )
        ]);
    }
    public static function get_course_list($categoryids, $userid): array
    {
        global $DB;

        $courserepository = new course_repository();
        try {
            $targetfield = $courserepository->get_field_controller('mc_moodle_zielgruppe', 'multiselect');
            $topicfield = $courserepository->get_field_controller('mc_moodle_themen', 'multiselect');
            $durationfield = $courserepository->get_field_controller('mc_moodle_kursdauer', 'select');
            $typefield = $courserepository->get_field_controller('mc_moodle_format', 'select');
        } catch (Exception $e) {
            echo $e->getMessage();
            exit;
        }

        $coursecache = \cache::make('core', 'course_image');
        $coursecardcontents = [];

        $categoryids = self::_extract_category_ids_from_input($categoryids);

        $coreCategories = [];
        foreach ($categoryids as $categoryid) {
            $coreCategories[] = core_course_category::get($categoryid);
        }

        foreach ($coreCategories as $category) {
            $targetedcourses = $category->get_courses(
                array('recursive' => true, 'sort' => array('sortorder' => 1))
            );
            $targetedcourses = array_filter($targetedcourses, function($course) {
                return $course->visible == 1;
            });

            foreach ($targetedcourses as $course) {
                $coursecategory = $category;
                if ($category->id !== $course->category) {
                    $coursecategory = core_course_category::get($course->category);
                }

                $cardcontent = new stdClass();
                $cardcontent->id = $course->id;
                $cardcontent->tags = core_tag_tag::get_item_tags('core', 'course', $course->id);

                $cardcontent->title = $course->fullname;
                $cardcontent->shortname = $course->shortname;
                $cardcontent->category = [
                    'id' => $coursecategory->id,
                    'value' => $coursecategory->name
                ];
                $cardcontent->image = $coursecache->get($course->id);
                $rawdescription =  $DB->get_field(
                    'course',
                    'summary',
                    array('id' => $course->id)
                );
                $cardcontent->description = strip_tags($rawdescription);

                $context = context_course::instance($course->id);
                $cardcontent->userEnrolled = is_enrolled($context, $userid);

                $rating = $DB->get_record_sql(
                    "SELECT
                            courseid,
                            COUNT(courseid) as reviewsnum,
                            AVG(rating) AS score
                        FROM {format_mintcampus_ratings}
                        WHERE courseid = :courseid
                        GROUP BY courseid",
                    array('courseid' => $course->id)
                );
                $cardcontent->score = $rating->score;
                $cardcontent->reviewsnum = $rating->reviewsnum;

                // course custom fields
                $metadata = $courserepository->get_course_metadata($course->id);
                $cardcontent->mcoriginal = isset($metadata['mcoriginal']) && $metadata['mcoriginal'];
                $cardcontent->mc_moodle_zielgruppe = [];
                foreach ($metadata['mc_moodle_zielgruppe'] as $targetgroupid) {
                    if (!$targetgroupid) continue;
                    $cardcontent->mc_moodle_zielgruppe[] = [
                        'id' => $targetgroupid,
                        'value' => \customfield_multiselect\field_controller::get_options_array($targetfield)[$targetgroupid]
                    ];
                }
                $cardcontent->mc_moodle_kursdauer = $durationfield->get_options()[$metadata['mc_moodle_kursdauer']];
                $cardcontent->mc_moodle_format = $typefield->get_options()[$metadata['mc_moodle_format']];
                $cardcontent->ismaterial = isset($metadata['ismaterial']) && $metadata['ismaterial'];
                $cardcontent->mc_moodle_themen = [];
                foreach ($metadata['mc_moodle_themen'] as $topicid) {
                    if ($topicid === null || $topicid === "") continue;
                    $cardcontent->mc_moodle_themen[] = [
                        'id' => (int)$topicid,
                        'value' => \customfield_multiselect\field_controller::get_options_array($topicfield)[$topicid]
                    ];
                }
                $cardcontent->senderName = $metadata['mc_moodle_partner_name'] ?: 'Absendername';

                $coursecardcontents[] = $cardcontent;
            }
        }

        return $coursecardcontents;
    }
    /**
     * Fields used for filtering have a format {id, value}
     * for matching targets by id rather than by value
     * @return external_multiple_structure
     */
    public static function get_course_list_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'course id'),
                    'title' => new external_value(PARAM_TEXT, 'course title'),
                    'shortname' => new external_value(PARAM_TEXT, 'short course title'),
                    'mcoriginal' => new external_value(PARAM_BOOL, 'produced by MINT-Campus'),
                    'category' => new external_single_structure(
                        [
                            'id' => new external_value(PARAM_INT, 'id'),
                            'value' => new external_value(PARAM_TEXT, 'value'),
                        ]
                    ),
                    'image' => new external_value(PARAM_TEXT, 'course teaser image'),
                    'description' => new external_value(PARAM_RAW, 'course description'),
                    'userEnrolled' => new external_value(PARAM_BOOL, 'target group'),
                    'score' => new external_value(PARAM_FLOAT, 'course average score'),
                    'reviewsnum' => new external_value(PARAM_INT, 'number of score reviews'),
                    'mc_moodle_zielgruppe' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'id'),
                                'value' => new external_value(PARAM_TEXT, 'value'),
                            ]
                        )
                    ),
                    'mc_moodle_kursdauer' => new external_value(PARAM_TEXT, 'course duration'),
                    'ismaterial' => new external_value(PARAM_BOOL, 'ismaterial'),
                    'mc_moodle_format' => new external_value(PARAM_TEXT, 'course type'),
                    'mc_moodle_themen' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'id'),
                                'value' => new external_value(PARAM_TEXT, 'value'),
                            ]
                        ),
                        'course topics (not related to topic course format)'
                    ),
                    'senderName' => new external_value(PARAM_TEXT, 'name of a course creator')
                ]
            )
        );
    }

    private static function _get_sub_categories_by(array $categoryids): array
    {
        global $DB;
        list($insql, $categoryids) = $DB->get_in_or_equal($categoryids);
        $sql = "select id, name, parent from {course_categories} where parent {$insql}";
        return $DB->get_records_sql($sql, $categoryids);
    }
    private static function _sanitize_and_filter_valid($ids): array
    {
        $ids = array_map(function($id) {
            return trim($id);
        }, $ids);

        return array_filter($ids, function($id) {
            return is_numeric($id) && $id != 0;
        });
    }
    private static function _filter_first_level_category_ids($ids): array
    {
        global $DB;
        list($insql, $params) = $DB->get_in_or_equal($ids);
        $topids = $DB->get_records_select('course_categories', "id $insql AND parent = 0", $params, '', 'id');
        return array_column($topids, 'id');
    }
    private static function _get_children_category_ids($parentids): array
    {
        global $DB;
        list($insql, $params) = $DB->get_in_or_equal($parentids);
        $childrenids = $DB->get_records_select('course_categories', "parent $insql", $params, '', 'id');
        return array_column($childrenids, 'id');
    }
    private static function _get_category_ids_of_level($level): array
    {
        global $DB;
        $needle = '/'; // counting slashes in the path
        $selectsql = 'ROUND((LENGTH(path) - LENGTH(REPLACE(path, "'. $needle .'", ""))) / LENGTH("'. $needle .'")) = ' . $level;
        $categoryids = $DB->get_records_select('course_categories', $selectsql);
        return array_column($categoryids, 'id');
    }
    private static function _extract_category_ids_from_input(string $input): array
    {
        $level = 1;
        if ($input) {
            try {
                $categoryids = explode(',', $input);
                $categoryids = self::_sanitize_and_filter_valid($categoryids);
                $topids = self::_filter_first_level_category_ids($categoryids);
                if (!empty($topids)) {
                    return self::_get_children_category_ids($topids);
                } else {
                    return self::_get_category_ids_of_level($level);
                }
            } catch (\Exception $e) {
                echo $e->getMessage();
                exit;
            }
        } else {
            return self::_get_category_ids_of_level($level);
        }
    }
}

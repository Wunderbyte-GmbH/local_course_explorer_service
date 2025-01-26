<?php

global $CFG;
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

require_once(__DIR__ . '/classes/course_repository.php');
class my_courses_provider extends external_api
{

    public static function get_my_courses_parameters()
    {
        return new external_function_parameters([
            'userid' => new external_value(
                PARAM_INT,
                'Id of a user viewing the course'
            )
        ]);
    }

    /**
     * @throws moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_my_courses($userid): array
    {
        global $DB;
        $courserepository = new course_repository();
        try {
            $topicfield = $courserepository->get_field_controller('mc_moodle_themen', 'multiselect');
            $durationfield = $courserepository->get_field_controller('mc_moodle_kursdauer', 'select');
            $typefield = $courserepository->get_field_controller('mc_moodle_format', 'select');
        } catch (Exception $e) {
            echo $e->getMessage();
            exit;
        }
        $coursecache = \cache::make('core', 'course_image');
        $coursecardcontents = [];

        $enrolledcourses = enrol_get_users_courses($userid);
        $favcourses = self::_get_user_favourite_courses($userid);
        $diffids = array_diff(array_keys($favcourses), array_keys($enrolledcourses));
        $favcourses = array_filter($favcourses, function($course) use ($diffids) {
            if (in_array($course->id, $diffids)) {
                return true;
            }
            return false;
        });
        $courses = array_merge($enrolledcourses, $favcourses);
        foreach ($courses as $course) {
            $category = \core_course_category::get($course->category, MUST_EXIST, true);
            $cardcontent = new stdClass();
            $cardcontent->id = $course->id;
            $cardcontent->title = trim($course->fullname);
            $cardcontent->category = $category->name ?? null;
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

            $metadata = $courserepository->get_course_metadata($course->id);
            $cardcontent->MCOriginal = isset($metadata['mcoriginal']) && $metadata['mcoriginal'];
            $cardcontent->duration = $durationfield->get_options()[$metadata['mc_moodle_kursdauer']];
            $cardcontent->courseType = $typefield->get_options()[$metadata['mc_moodle_format']];
            $cardcontent->topics = [];
            foreach ($metadata['mc_moodle_themen'] as $topicid) {
                if ($topicid === null || $topicid === "") continue;
                $cardcontent->topics[] = [
                    'id' => (int)$topicid,
                    'value' => \customfield_multiselect\field_controller::get_options_array($topicfield)[$topicid]
                ];
            }

            $cardcontent->favourite = self::_is_course_favourite($userid, $course->id);
            $cardcontent->senderName = $metadata['mc_moodle_partner_name'] ?: 'Absendername';
            $coursecardcontents[] = $cardcontent;
        }
//        var_dump($coursecardcontents);die;
        return $coursecardcontents;
    }

    public static function get_my_courses_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'course id'),
                    'title' => new external_value(PARAM_TEXT, 'course title'),
                    'MCOriginal' => new external_value(PARAM_BOOL, 'produced by MINT-Campus'),
                    'category' => new external_value(PARAM_TEXT, 'Category name'),
                    'image' => new external_value(PARAM_TEXT, 'course teaser image'),
                    'description' => new external_value(PARAM_RAW, 'course description'),
                    'userEnrolled' => new external_value(PARAM_BOOL, 'course type'),
                    'score' => new external_value(PARAM_FLOAT, 'course average score'),
                    'reviewsnum' => new external_value(PARAM_INT, 'number of score reviews'),
                    'favourite' => new external_value(PARAM_BOOL, 'favourite status of a course'),
                    'duration' => new external_value(PARAM_TEXT, 'course duration'),
                    'courseType' => new external_value(PARAM_TEXT, 'course type'),
                    'topics' =>  new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'id'),
                                'value' => new external_value(PARAM_TEXT, 'value')
                            ]
                        ),
                        'course topics (not related to topic course format)'
                    ),
                    'senderName' => new external_value(PARAM_TEXT, 'name of a course creator')
                ]
            )
        );
    }

    public static function get_my_course_ids_parameters()
    {
        return new external_function_parameters([
            'useremail' => new external_value(
                PARAM_TEXT,
                'Email of a user adding/removing a course to/from favourites'
            )
        ]);
    }

    /**
     * I return a list of my courses (where given user is enrolled)
     * and a list of my favourite courses (that user marked as a favourite)
     * @param $useremail
     * @return array
     * @throws moodle_exception
     */
    public static function get_my_course_ids($useremail): array
    {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['email' => $useremail]);
        if (!$userid) {
            return [
                'courses' => [],
                'favorites' => []
            ];
        }

        $returndata = array();
        $courses = enrol_get_users_courses($userid);
        $courseids = array_column($courses, 'id');
        $favouritecourseids = [];

        foreach ($courseids as $courseid) {
            if (self::_is_course_favourite($userid, $courseid)){
                $favouritecourseids[] = $courseid;
            }
        }

        $returndata['courses'] = $courseids;
        $returndata['favorites'] = $favouritecourseids;

        return $returndata;
    }

    public static function get_my_course_ids_returns()
    {
        return new external_single_structure(
            array(
                'courses' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'id')
                ),
                'favorites' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'id')
                ),
            )
        );
    }

    /**
     * @throws moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    private static function _get_user_favourite_courses($userid)
    {
        global $DB;
        $usercontext = \context_user::instance($userid);
        $ufservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);
        $favourites = $ufservice->find_favourites_by_type('core_course', 'courses');
        if (empty($favourites)) return $favourites;

        $favcourseids = array_column($favourites, 'itemid');
        list($insql, $params) = $DB->get_in_or_equal($favcourseids);
        return $DB->get_records_select('course', "id $insql", $params, '', 'id,category,fullname');
    }

    /**
     * Fetch list of users courses with their shortnames
     * @param int|string $userid User's Id or username
     * @return bool SQL Query that returns resultset containing columns userid, itemid, categoryid, sortorder, fullname, shortname
     * @throws moodle_exception
     */
    private static function _is_course_favourite($userid, $courseid): bool
    {
        $usercontext = \context_user::instance($userid);
        $ufservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);
        $favourites = $ufservice->find_favourites_by_type('core_course', 'courses');
        foreach ($favourites as $favourite) {
            if ($favourite->itemid == $courseid) {
                return true;
            }
        }
        return false;
    }
}
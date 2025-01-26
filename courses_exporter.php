<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

require_once(__DIR__ . '/classes/course_repository.php');

class courses_exporter extends external_api
{
    public static function export_courses_parameters()
    {
        return new external_function_parameters([
            'categoryids' => new external_value(
                PARAM_TEXT,
                'Ids of top categories, whose courses (sub-categories) are displayed'
            )
        ]);
    }
    public static function export_courses($categoryids): array
    {
        global $DB, $CFG;

        $courserepository = new course_repository();
        try {
            $durationfield = $courserepository->get_field_controller('mc_moodle_kursdauer', 'select');
            $targetfield = $courserepository->get_field_controller('mc_moodle_zielgruppe', 'multiselect');
            $typefield = $courserepository->get_field_controller('mc_moodle_format', 'select');
            $levelfield = $courserepository->get_field_controller('mc_moodle_level', 'select');
            $copyrightfield = $courserepository->get_field_controller('mc_moodle_copyright', 'select');
            $certfield = $courserepository->get_field_controller('mc_moodle_zertifikat', 'select');
            $languagefield = $courserepository->get_field_controller('mc_moodle_sprache', 'select');
            $topicfield = $courserepository->get_field_controller('mc_moodle_themen', 'multiselect');
            $maingroup = $courserepository->get_field_controller('mc_zielgruppen', 'multiselect');

        } catch (Exception $e) {
            echo $e->getMessage();
            exit;
        }
        $coursecache = \cache::make('core', 'course_image');
        $coursecardcontents = [];

        $categoryids = self::_extract_category_ids_from_input($categoryids);

        $coreCategories = [];
        foreach ($categoryids as $categoryid) {
            if (!in_array($categoryid, [7, 6])) {
                $coreCategories[] = core_course_category::get($categoryid);
            }
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
                $cardcontent->title = $course->fullname;
                $cardcontent->shortname = $course->shortname;
                $tagrecords = core_tag_tag::get_item_tags('core', 'course', $course->id);
                $tags = [];
                foreach($tagrecords as $tag) {
                    $tags[] = $tag->rawname;
                    $tags[] = $tag->name;
                }

                $cardcontent->tags = implode(",", $tags);
                $cardcontent->category = [
                    'id' => $coursecategory->id,
                    'value' => $coursecategory->name
                ];
                $cardcontent->featureimage = self::_evaluate($coursecache->get($course->id));
                $coursecontext = context_course::instance($course->id);
                $imagesource = $DB->get_field_select(
                    'files',
                    'author',
                    "contextid = :contextid and component = :component and filearea = :filearea and filename != :dir",
                    [
                        'contextid' => $coursecontext->id,
                        'component' => 'course',
                        'filearea' => 'overviewfiles',
                        'dir' => '.'
                    ]
                );
                $timemodified = $DB->get_field_select(
                    'files',
                    'timemodified',
                    "contextid = :contextid and component = :component and filearea = :filearea and filename != :dir",
                    [
                        'contextid' => $coursecontext->id,
                        'component' => 'course',
                        'filearea' => 'overviewfiles',
                        'dir' => '.'
                    ]
                );

                $cardcontent->mc_moodle_quelle = self::_get_image_source_html($imagesource);
                $cardcontent->imgtimemodified = $timemodified;

                $rawdescription = $DB->get_field(
                    'course',
                    'summary',
                    array('id' => $course->id)
                );
                $cardcontent->content = self::_evaluate(strip_tags($rawdescription, array('<br>','<em>','<strong>', '<a>')));

                // course custom fields
                $metadata = $courserepository->get_course_metadata($course->id);
                $cardcontent->excerpt = self::_evaluate($metadata['excerpt']);
                $cardcontent->mc_label_bool = isset($metadata['mcoriginal']) && $metadata['mcoriginal'];
                $cardcontent->mc_label = $cardcontent->mc_label_bool ? get_string('label', 'local_course_explorer_service') : "";
                if ($cardcontent->mc_label == "") {
                    $cardcontent->mc_label_bool = 0;
                }
                $cardcontent->mc_moodle_dauer_text = self::_evaluate($durationfield->get_options()[$metadata['mc_moodle_kursdauer']]);
                $cardcontent->mc_moodle_dauer = $metadata['mc_moodle_kursdauer'];
                $cardcontent->mc_moodle_lernziele = self::_aggregate_learning_goals_from_metadata($metadata);
                $cardcontent->mc_moodle_kosten = $metadata['mc_moodle_kosten'] ?: get_string('for_free', 'local_course_explorer_service');
                $cardcontent->mc_zielgruppe = [];
                foreach ($metadata['mc_moodle_zielgruppe'] as $targetgroupid) {
                    $cardcontent->mc_zielgruppe[] = [
                        'id' => (int)$targetgroupid,
                        'value' => \customfield_multiselect\field_controller::get_options_array($targetfield)[$targetgroupid]
                    ];
                }
                $cardcontent->mc_hauptzielgruppe = [];
                foreach ($metadata['mc_zielgruppen'] as $targetgroupid) {
                    $cardcontent->mc_hauptzielgruppe[] = [
                        'id' => (int)$targetgroupid,
                        'value' => \customfield_multiselect\field_controller::get_options_array($maingroup)[$targetgroupid]
                    ];
                }
                $cardcontent->mc_format_text = self::_evaluate($typefield->get_options()[$metadata['mc_moodle_format']]);
                $cardcontent->mc_format = $metadata['mc_moodle_format'];
                $cardcontent->mc_moodle_level_text = self::_evaluate($levelfield->get_options()[$metadata['mc_moodle_level']]);
                $cardcontent->mc_moodle_level = $metadata['mc_moodle_level'];
                $cardcontent->mc_moodle_copyright = self::_evaluate($copyrightfield->get_options()[$metadata['mc_moodle_copyright']]);
                $cardcontent->mc_moodle_zertifikat_text = self::_evaluate($certfield->get_options()[$metadata['mc_moodle_zertifikat']]);
                $cardcontent->mc_moodle_zertifikat = $metadata['mc_moodle_zertifikat'];
                $cardcontent->mc_moodle_sprache_text = self::_evaluate($languagefield->get_options()[$metadata['mc_moodle_sprache']]);
                $cardcontent->mc_moodle_sprache = $metadata['mc_moodle_sprache'];
                $cardcontent->mc_moodle_youtube_link = $metadata['mc_youtube_link'];
                $cardcontent->mc_themen = [];
                foreach ($metadata['mc_moodle_themen'] as $topicid) {
                    $cardcontent->mc_themen[] = [
                        'id' => $topicid,
                        'value' => \customfield_multiselect\field_controller::get_options_array($topicfield)[$topicid]
                    ];
                }

                // course sections
                $sections = $DB->get_records_select(
                    'course_sections',
                    "course = :course AND section != 0 AND visible = 1",
                    ['course' => $course->id],
                    'section'
                );
                $cardcontent->mc_moodle_module = [];
                foreach ($sections as $section) {
                    $sectionTitle = format_string($section->name);
                    $sectionDescription = format_text($section->summary, $section->summaryformat);

                    if ($sectionTitle || $sectionDescription) {
                        $cardcontent->mc_moodle_module[] = [
                            'mc_moodle_modultitel' => self::_evaluate($sectionTitle),
                            'mc_moodle_modullernergebnis' => self::_evaluate($sectionDescription)
                        ];
                    }
                }
                $cardcontent->mc_moodle_partner_url = $metadata['mc_moodle_partner_url'];
                $cardcontent->mc_moodle_partner_name = self::_evaluate($metadata['mc_moodle_partner_name']);
                $cardcontent->mc_moodle_partner_info = self::_evaluate(strip_tags($metadata['mc_moodle_partner_info']));
                $cardcontent->mc_moodle_partner_bild = $metadata['mc_moodle_partner_image'] ?: $CFG->local_course_explorer_service_fallback_img_url;
                $cardcontent->mc_moodle_partner_url2 = $metadata['mc_moodle_partner_url2'];
                $cardcontent->mc_moodle_partner_name2 = self::_evaluate($metadata['mc_moodle_partner_name2']);
                $cardcontent->mc_moodle_partner_info2 = self::_evaluate(strip_tags($metadata['mc_moodle_partner_info2']));
                $cardcontent->mc_moodle_partner_bild2 = $metadata['mc_moodle_partner_image2'] ?: $CFG->local_course_explorer_service_fallback_img_url;
                $cardcontent->mc_moodle_partner_url3 = $metadata['mc_moodle_partner_url3'];
                $cardcontent->mc_moodle_partner_name3 = self::_evaluate($metadata['mc_moodle_partner_name3']);
                $cardcontent->mc_moodle_partner_info3 = self::_evaluate(strip_tags($metadata['mc_moodle_partner_info3']));
                $cardcontent->mc_moodle_partner_bild3 = $metadata['mc_moodle_partner_image3'] ?: $CFG->local_course_explorer_service_fallback_img_url;
                $cardcontent->mc_moodle_partner_url4 = $metadata['mc_moodle_partner_url4'];
                $cardcontent->mc_moodle_partner_name4 = self::_evaluate($metadata['mc_moodle_partner_name4']);
                $cardcontent->mc_moodle_partner_info4 = self::_evaluate(strip_tags($metadata['mc_moodle_partner_info4']));
                $cardcontent->mc_moodle_partner_bild4 = $metadata['mc_moodle_partner_image4'] ?: $CFG->local_course_explorer_service_fallback_img_url;
                $cardcontent->mc_moodle_empfehlungstext = $metadata['mc_moodle_emfehlungstext'] ?: get_string('fallback_recommendation', 'local_course_explorer_service');
                $cardcontent->mc_moodle_empfehlungsabsender = $metadata['mc_moodle_emfehlungsabsender'] ?: get_string('fallback_recommendation_issuer', 'local_course_explorer_service');
                $cardcontent->mc_moodle_empfehlungsbild = $metadata['mc_moodle_empfehlungsbild'] ?: $CFG->local_course_explorer_service_fallback_img_url;
                $cardcontent->mc_moodle_barrierearm_bool = isset($metadata['mc_moodle_barrierearm']) && $metadata['mc_moodle_barrierearm'];
                $cardcontent->mc_moodle_barrierearm = (int) $cardcontent->mc_moodle_barrierearm_bool;
                $cardcontent->mc_moodle_video = self::_format_mintcampus_get_video($course->id) ? self::_get_teaser_endpoint_url('video', $course->id) : null;
                $cardcontent->mc_moodle_image = self::_format_mintcampus_get_image($course->id) ? self::_get_teaser_endpoint_url('image', $course->id) : self::_evaluate(null);

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
                $cardcontent->mc_moodle_score = $rating->score;
                $cardcontent->mc_moodle_reviewsnum = $rating->reviewsnum;

                global $PAGE;
                require_once($CFG->dirroot . '/enrol/locallib.php');
                $manager = new course_enrolment_manager($PAGE, $course);
                $instances = $manager->get_enrolment_instances();
                $hasguestaccess = 0;
                foreach ($instances as $instance) {
                    if ($instance->enrol == 'guest' && $instance->status == 0) {
                        $hasguestaccess = 1;
                    }
                }
                $cardcontent->mc_moodle_needs_enrolment = !$hasguestaccess;

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
    public static function export_courses_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'course id'),
                    'title' => new external_value(PARAM_TEXT, 'course title'),
                    'shortname' => new external_value(PARAM_TEXT, 'course title'),
                    'featureimage' => new external_value(PARAM_TEXT, 'course teaser image'),
                    'mc_moodle_quelle' => new external_value(PARAM_RAW, 'image source (HTML + URL)'),
                    'content' => new external_value(PARAM_RAW, 'course description'),
                    'excerpt' => new external_value(PARAM_RAW, 'short course description'),
                    'category' => new external_single_structure(
                        [
                            'id' => new external_value(PARAM_INT, 'id'),
                            'value' => new external_value(PARAM_TEXT, 'value'),
                        ]
                    ),
                    'tags' => new external_value(PARAM_TEXT, 'comma seperated tag list'),
                    'imgtimemodified' => new external_value(PARAM_RAW, 'timemodified of img'),
                    'mc_moodle_dauer_text' => new external_value(PARAM_TEXT, 'course duration text'),
                    'mc_moodle_dauer' => new external_value(PARAM_INT, 'course duration id'),
                    'mc_moodle_lernziele' => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'learning results')
                    ),
                    'mc_moodle_kosten' => new external_value(PARAM_TEXT, 'course cost'),
                    'mc_moodle_level_text' => new external_value(PARAM_TEXT, 'difficulty level text'),
                    'mc_moodle_level' => new external_value(PARAM_TEXT, 'difficulty level id'),
                    'mc_moodle_copyright' => new external_value(PARAM_TEXT, 'license'),
                    'mc_moodle_zertifikat_text' => new external_value(PARAM_TEXT, 'certificate type text'),
                    'mc_moodle_zertifikat' => new external_value(PARAM_TEXT, 'certificate type id'),
                    'mc_moodle_sprache_text' => new external_value(PARAM_TEXT, 'course language text'),
                    'mc_moodle_sprache' => new external_value(PARAM_INT, 'course language id'),
                    'mc_moodle_barrierearm_bool' => new external_value(PARAM_BOOL, 'produced by MINT-Campus'),
                    'mc_moodle_barrierearm' => new external_value(PARAM_INT, 'produced by MINT-Campus'),
                    'mc_themen' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'id'),
                                'value' => new external_value(PARAM_TEXT, 'value'),
                            ]
                        ),
                        'course topics (not related to topic course format)'
                    ),
                    'mc_moodle_module' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'mc_moodle_modultitel' => new external_value(PARAM_TEXT, 'course section title'),
                                'mc_moodle_modullernergebnis' => new external_value(PARAM_RAW, 'course section description'),
                            ]
                        )
                    ),
                    'mc_moodle_partner_name' => new external_value(PARAM_TEXT, 'name of a course creator'),
                    'mc_moodle_partner_info' => new external_value(PARAM_TEXT, 'info about a creator'),
                    'mc_moodle_partner_bild' => new external_value(PARAM_TEXT, "creator's image/logo"),
                    'mc_moodle_partner_url' => new external_value(PARAM_TEXT, "URL or corresponding 'MINT Vernetzt' profile"),
                    'mc_moodle_partner_name2' => new external_value(PARAM_TEXT, 'name of a course creator'),
                    'mc_moodle_partner_info2' => new external_value(PARAM_TEXT, 'info about a creator'),
                    'mc_moodle_partner_bild2' => new external_value(PARAM_TEXT, "creator's image/logo"),
                    'mc_moodle_partner_url2' => new external_value(PARAM_TEXT, "URL or corresponding 'MINT Vernetzt' profile"),
                    'mc_moodle_partner_name3' => new external_value(PARAM_TEXT, 'name of a course creator'),
                    'mc_moodle_partner_info3' => new external_value(PARAM_TEXT, 'info about a creator'),
                    'mc_moodle_partner_bild3' => new external_value(PARAM_TEXT, "creator's image/logo"),
                    'mc_moodle_partner_url3' => new external_value(PARAM_TEXT, "URL or corresponding 'MINT Vernetzt' profile"),
                    'mc_moodle_partner_name4' => new external_value(PARAM_TEXT, 'name of a course creator'),
                    'mc_moodle_partner_info4' => new external_value(PARAM_TEXT, 'info about a creator'),
                    'mc_moodle_partner_bild4' => new external_value(PARAM_TEXT, "creator's image/logo"),
                    'mc_moodle_partner_url4' => new external_value(PARAM_TEXT, "URL or corresponding 'MINT Vernetzt' profile"),
                    'mc_moodle_youtube_link' => new external_value(PARAM_TEXT, "Youtube Link"),
                    'mc_moodle_empfehlungstext' => new external_value(PARAM_RAW, "Recommendation text"),
                    'mc_moodle_empfehlungsabsender' => new external_value(PARAM_TEXT, "Author of a recommendation"),
                    'mc_moodle_empfehlungsbild' => new external_value(PARAM_TEXT, "Recommendation image"),
                    'mc_label_bool' => new external_value(PARAM_BOOL, 'produced by MINT-Campus'),
                    'mc_label' => new external_value(PARAM_TEXT, 'produced by MINT-Campus (label text)'),
                    'mc_format_text' => new external_value(PARAM_TEXT, 'course type text'),
                    'mc_format' => new external_value(PARAM_INT, 'course type id'),
                    'mc_zielgruppe' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'id'),
                                'value' => new external_value(PARAM_TEXT, 'value'),
                            ]
                        ),
                        'target groups for a course'
                    ),
                    'mc_hauptzielgruppe' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'id'),
                                'value' => new external_value(PARAM_TEXT, 'value'),
                            ]
                        ),
                        'target groups for a course'
                    ),
                    'mc_moodle_video' => new external_value(PARAM_TEXT, 'URL to a format course video'),
                    'mc_moodle_image' => new external_value(PARAM_TEXT, 'URL to a format course image'),
                    'mc_moodle_score' => new external_value(PARAM_FLOAT, 'course average score'),
                    'mc_moodle_reviewsnum' => new external_value(PARAM_INT, 'number of score reviews'),
                    'mc_moodle_needs_enrolment' => new external_value(PARAM_BOOL, 'requirement to enroll into a course'),
                ]
            )
        );
    }

    private static function _evaluate($input): string
    {
        if (empty($input)) {
            return '';
        }
        return $input;
    }

    private static function _get_image_source_html($inputString): string
    {
        if (!$inputString = self::_evaluate($inputString)) {
            return $inputString;
        }

        $html = preg_replace('/\$(.*?)\$/', '<a href="$1" target="_blank">$1</a>', $inputString);

        return "<span class='image-source'>$html</span>";
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
                return [
                    'courses' => [],
                    'errorMessage' => $e->getMessage()
                ];
            }
        } else {
            return self::_get_category_ids_of_level($level);
        }
    }
    private static function _aggregate_learning_goals_from_metadata($metadata): array
    {
        $num = 3;
        $namebase = 'mc_moodle_lernergebnis_';
        $learninggoals = array();
        for ($i = 1; $i <= $num; $i++) {
            if (!empty($metadata[$namebase . $i])) {
                $learninggoals[] = $metadata[$namebase . $i];
            }
        }

        return $learninggoals;
    }

    private static function _format_mintcampus_get_video($courseid): ?string
    {
        return self::_get_file_from_mintcampus_format('video', $courseid);
    }

    private static function _format_mintcampus_get_image($courseid): ?string
    {
        return self::_get_file_from_mintcampus_format('image', $courseid);
    }

    private static function _get_file_from_mintcampus_format($type, $courseid): ?string
    {
        global $DB;
        $context = \context_course::instance($courseid);
        $itemid = $DB->get_field('course_format_options', 'value', array(
            'courseid' => $courseid,
            'format' => 'mintcampusformat',
            'sectionid' => 0,
            'name' => "mintcampuscourse{$type}_filemanager"
        ));
        if (!$itemid) {
            $itemid = file_get_unused_draft_itemid();
        }
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'format_mintcampus', "mintcampuscourse{$type}_filearea", $itemid);
        if ($files) {
            foreach ($files as $file) {
                $url = moodle_url::make_pluginfile_url(
                    $context->id,
                    'format_mintcampus',
                    "mintcampuscourse{$type}_filearea",
                    $itemid,
                    $file->get_filepath(),
                    $file->get_filename()
                );
            }
            return isset($url) && $url->__toString() ? $url->__toString() : null;
        } else {
            return null;
        }
    }

    /**
     * @throws moodle_exception
     */
    private static function _get_teaser_endpoint_url(string $type, $courseid): string
    {
        $url = (new moodle_url(
            '/local/mintcampus_generic/show_course_teaser.php',
            [
                'courseid' => $courseid,
                'type' => $type
            ]
        ))->__toString();
        $url = str_replace('amp;', '', $url);
        return urldecode($url);
    }
}

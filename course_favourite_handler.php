<?php
require_once($CFG->libdir . '/externallib.php');
class course_favourite_handler extends external_api
{
    public static function set_favourite_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'useremail' => new external_value(
                PARAM_TEXT,
                'Email of a user adding/removing a course to/from favourites'
            ),
            'firstname' => new external_value(
                PARAM_TEXT,
                'Email of a user adding/removing a course to/from favourites'
            ),
            'lastname' => new external_value(
                PARAM_TEXT,
                'Email of a user adding/removing a course to/from favourites'
            ),
            'courseid' => new external_value(
                PARAM_INT,
                'Id of a course added/removed to/from favourites'
            ),
            'favourite' => new external_value(
                PARAM_BOOL,
                'Status flag whether a course added/removed to/from favourites'
            ),
        ]);
    }
    public static function set_favourite($useremail, $firstname, $lastname, $courseid, $favourite): array
    {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/user/lib.php');

        try {
            $userid = $DB->get_field('user', 'id', ['email' => $useremail]);
            if (!$userid) {
                if (empty($firstname) || empty($lastname)) {
                    throw new Exception("Can't create user, since first- and lastname not provided. Skipping...");
                }

                $newuser = new stdClass();
                $newuser->auth = 'oauth2';
                $newuser->confirmed = 1;
                $newuser->mnethostid = $CFG->mnet_localhost_id;
                $newuser->email = $useremail;
                $newuser->username = $useremail;
                $newuser->firstname = $firstname;
                $newuser->lastname = $lastname;

                $userid = user_create_user($newuser, false);
            }

            $ufservice = \core_favourites\service_factory::get_service_for_user_context(\context_user::instance($userid));
            $favouriteexists = $ufservice->favourite_exists('core_course', 'courses', $courseid,
                \context_course::instance($courseid));

            if ($favourite) {
                if (!$favouriteexists) {
                    $ufservice->create_favourite('core_course', 'courses', $courseid,
                        \context_course::instance($courseid));
                } else {
                    throw new Exception("Course (id=$courseid) has already been added to favourites.");
                }
            } else {
                if ($favouriteexists) {
                    $ufservice->delete_favourite('core_course', 'courses', $courseid,
                        \context_course::instance($courseid));
                } else {
                    throw new Exception("Course (id=$courseid) is not in favourites.");
                }
            }

            return [
                'success' => true,
                'message' => ''
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    public static function set_favourite_returns(): external_single_structure
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Whether function execution was successful'),
                'message' => new external_value(PARAM_TEXT, 'Additional message (mostly error message)')
            )
        );
    }
}
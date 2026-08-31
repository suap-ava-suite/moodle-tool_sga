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
 * SGA Integration
 *
 * This module provides extensive analytics on a platform of choice
 * Currently support Google Analytics and Piwik
 *
 * @package     tool_sga
 * @category    upgrade
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sga;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Authentication is token-based, performed by service::authenticate().
require_once('../../../../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/enrol/externallib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/admin/tool/sga/locallib.php');
require_once($CFG->dirroot . '/admin/tool/sga/api/servicelib.php');
require_once($CFG->dirroot . '/admin/tool/sga/classes/Jsv4/ValidationException.php');
require_once($CFG->dirroot . '/admin/tool/sga/classes/Jsv4/Validator.php');
require_once(__DIR__ . '/sync_up_enrolments_helper.php');


/**
 * SGA enrolment synchronization service.
 *
 * Receives a JSON payload describing categories, courses, users, cohorts, enrolments and groups,
 * and synchronizes them into this Moodle installation.
 *
 * @package     tool_sga
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_up_enrolments_service extends service {
    use sync_up_enrolments_helper;

    /** @var array URLs of the synced entities, indexed by type and natural key. */
    private $urls = [];
    /** @var string[] error messages collected while processing the request. */
    private $errors = [];
    /** @var string[] success messages collected while processing the request. */
    private $successes = [];
    /** @var \stdClass the decoded request JSON payload. */
    private $json;


    /**
     * Reads the request body and runs the full synchronization.
     *
     * @return array the "urls", "erros" and "successes" produced by the synchronization.
     */
    public function do_call() {
        global $CFG;
        $jsonstring = file_get_contents('php://input');

        $result = $this->process($jsonstring, true);
        $this->insertsyncdb($jsonstring);

        return $result;
    }


    /**
     * Validates and synchronizes the entities described in the request JSON.
     *
     * @param string $jsonstring the raw JSON request body.
     * @param bool $assync whether to run the full synchronization, or just categories and courses.
     * @return array the "urls", "erros" and "successes" produced by the synchronization.
     */
    public function process($jsonstring, $assync) {
        global $CFG;

        $this->validate_json($jsonstring);
        $this->sync_categories();
        $this->sync_courses();
        if ($assync) {
            $this->import_template_courses_backup();
            $this->sync_users();
            $this->sync_cohorts();
            $this->sync_cohorts_members();
            $this->sync_enrols();
            $this->sync_enrolments();
            $this->sync_groups();
            $this->sync_groups_members();
        }

        return [
            "urls" => $this->urls,
            'erros' => $this->errors,
            'successes' => $this->successes,
        ];
    }


    /**
     * Synchronizes course categories from the request JSON.
     *
     * @return void
     */
    public function sync_categories() {
        $this->request_iterator(
            'categories',
            ['id', 'parent', 'sortorder', 'coursecount', 'visibleold', 'timemodified', 'depth', 'path'],
            ['idnumber', 'name', 'visible'],
            function ($tosync, $i) {
                $ondb = $this->get_category_by_idnumber($tosync->idnumber);
                $tosync->naturalkey = $tosync->idnumber;
                $tosync->op = $ondb ? 'UPD' : 'ADD';
                if ($tosync->op == 'ADD') {
                    $data = [
                        'idnumber' => $tosync->idnumber,
                        'name' => $tosync->name,

                        'description' => isset($tosync->description) ? $tosync->description : null,
                        'descriptionformat' => isset($tosync->descriptionformat) ? $tosync->descriptionformat : 0,
                        'visible' => isset($tosync->visible) ? $tosync->visible : 1,
                        'theme' => isset($tosync->theme) ? $tosync->theme : '',
                        'parent' => $this->get_parent_id($tosync),
                    ];

                    $ondb = \core_course_category::create($data);
                } else if (isset($this->json->categories->update_fields)) {
                    $data = $this->set_updatable_fields($tosync, [], $this->json->categories->update_fields);
                    if (in_array('parent_idnumber', $this->json->categories->update_fields)) {
                        $this->_set_parent($data, $tosync);
                    }

                    if (count($data) > 0) {
                        $ondb->update($data);
                        unset($this->categories[$tosync->idnumber]);
                        $ondb = $this->get_category_by_idnumber($tosync->idnumber);
                    }
                }
                $this->urls['categories'][$tosync->idnumber] = "{$CFG->wwwroot}/course/index.php?categoryid={$ondb->id}";
                return $ondb;
            }
        );
    }


    /**
     * Synchronizes courses from the request JSON.
     *
     * @return void
     */
    public function sync_courses() {
        $this->request_iterator(
            'courses',
            ['id', 'category', 'sortorder', 'originalcourseid', 'timecreated', 'timemodified'],
            ['category_idnumber', 'fullname', 'shortname', 'idnumber'],
            function ($course, $i) {
                $course->naturalkey = $course->idnumber;
                $catetoryondb = $this->get_cached('course_categories', 'idnumber', $course->category_idnumber, true);

                $ondb = $this->get_cached('course', 'idnumber', $course->idnumber);
                $course->op = $ondb ? 'UPD' : 'ADD';
                if ($course->op === 'ADD') {
                    $course->category = $catetoryondb->id;
                    \create_course($course);
                    $ondb = $this->get_cached('course', 'idnumber', $course->idnumber);
                } else if (isset($this->json->courses->update_fields)) {
                    $data = $this->set_updatable_fields($course, [], $this->json->courses->update_fields);
                    if (count($data) > 0) {
                        \update_course((object)$data);
                        unset($this->courses[$course->idnumber]);
                        $ondb = $this->get_cached('course', 'idnumber', $course->idnumber);
                    }
                }
                $this->urls["courses"][$ondb->idnumber] = "{$CFG->wwwroot}/course/view.php?id={$ondb->id}";
                return $ondb;
            }
        );
    }


    /**
     * Backs up and restores template courses into newly created courses that reference one.
     *
     * @return void
     */
    public function import_template_courses_backup() {
        // phpcs:ignore moodle.Commenting.TodoComment.MissingInfoInline, moodle.Files.LineLength.TooLong
        // TODO: Fazer de forma assíncrona, ou seja, não haverá o atributo `$course->op` no JSON e tenho que decidir como proceder.
        global $CFG, $DB;

        $courseswithtemplates = array_filter($this->json->courses->list ?? [], function ($course) {
            return isset($course->template_path) && $course->op == 'ADD';
        });
        foreach ($courseswithtemplates as $course) {
            if (!$ondb = $this->get_cached('course', 'idnumber', $course->idnumber)) {
                continue;
            };

            if (!$templatecourse = $this->get_template_course($course)) {
                continue;
            }

            if (!$backupresult = $this->backup_template($templatecourse)) {
                $this->errors[] = "Não foi possível restaurar o modelo {$templatecourse} para o curso {$course->idnumber}.";
                continue;
            }

            if ($this->restore_into_course($backupresult['backup_destination'], $ondb)) {
                $message = "Backup do curso $templatecourse->idnumber ($templatecourse->id) restaurado "
                    . "com sucesso no curso $ondb->idnumber ($ondb->id).";
                $this->successes[] = $message;
            } else {
                $message = "Falha na verificação prévia da restauração Backup do curso $templatecourse->idnumber "
                    . "($templatecourse->id) restaurado com sucesso no curso $ondb->idnumber ($ondb->id).";
                $this->errors[] = $message;
            }
        }
    }


    /**
     * Synchronizes users from the request JSON.
     *
     * @return void
     */
    public function sync_users() {
        global $CFG;
        $this->request_iterator(
            'users',
            [
                'id', 'timecreated', 'timemodified', 'lastlogin', 'firstaccess', 'lastaccess', 'currentlogin',
                'lastip', 'secret', 'description', 'descriptionformat', 'htmleditor', 'mailformat', 'maildigest',
                'maildisplay', 'autosubscribe', 'trackforums', 'trustbitmask', 'calendartype', 'mnethostid',
                'moodlenetprofile',
            ],
            ['username', 'auth', 'firstname', 'lastname', 'email', 'password', "active"],
            function ($user, $i) {
                $rawpassword = trim($user->password ?? "");
                $password = !empty($rawpassword) ? hash_internal_user_password($user->password) : AUTH_PASSWORD_NOT_CACHED;

                $user->naturalkey = $user->username;
                $ondb = $this->get_cached('user', 'username', $user->naturalkey);
                $user->op = $ondb ? 'UPD' : 'ADD';
                if ($user->op == 'ADD') {
                    $data = (array)$user;
                    $data['password'] = $password;
                    unset($data['user_preferences']);
                    unset($data['custom_fields']);

                    \user_create_user($data, ($password != AUTH_PASSWORD_NOT_CACHED));

                    $ondb = $this->get_cached('user', 'username', $user->naturalkey);

                    $ondb->is_new = true;

                    foreach ($user->user_preferences ?? [] as $key => $value) {
                        \set_user_preference($key, $value, $ondb);
                    }
                } else if (isset($this->json->users->update_fields)) {
                    $data = $this->set_updatable_fields($user, [], $this->json->users->update_fields);
                    if (count($data) > 0) {
                        if ($password != AUTH_PASSWORD_NOT_CACHED) {
                            $data['password'] = $password;
                        } else {
                            unset($data['password']);
                        }

                        $data['id'] = $ondb->id;
                        \user_update_user($data);
                        unset($this->users[$user->username]);
                        $ondb = $this->get_cached('user', 'username', $user->naturalkey);
                    }
                }

                if (isset($user->custom_fields)) {
                    \profile_save_custom_fields($ondb->id, (array)($user->custom_fields));
                }

                if (isset($user->picture) && !empty($user->picture) && $user->picture != $ondb->picture) {
                    require_once($CFG->dirroot . '/user/profile/lib.php');
                    $usercontext = \context_user::instance($ondb->id);
                    $fs = \get_file_storage();
                    $fs->delete_area_files($usercontext->id, 'user', 'icon');
                    $user->picture = \profile_save_data($usercontext, 'icon', $user->picture);
                    $ondb->picture = $user->picture;
                    $DB->update_record('user', (object)['id' => $ondb->id, 'picture' => $ondb->picture]);
                }

                $this->urls['users'][$user->naturalkey] = "{$CFG->wwwroot}/user/view.php?id={$ondb->id}";
                return $ondb;
            }
        );
    }


    /**
     * Synchronizes cohorts from the request JSON.
     *
     * @return void
     */
    public function sync_cohorts() {
        $this->request_iterator(
            'cohorts',
            ['id', 'timecreated', 'timemodified'],
            ['idnumber', 'contextid', 'visible'],
            function ($tosync, $i) {
                global $CFG, $DB;
                $tosync->naturalkey = $tosync->idnumber;
                $ondb = $DB->get_record('cohort', ['idnumber' => $tosync->idnumber]);
                $tosync->op = $ondb ? 'UPD' : 'ADD';
                if ($tosync->op == 'ADD') {
                    $dbid = \cohort_add_cohort($tosync);
                    $ondb = $DB->get_record('cohort', ['id' => $dbid]);
                    $this->cohorts[$tosync->naturalkey] = $ondb;
                } else if (isset($this->json->cohorts->update_fields)) {
                    $data = $this->set_updatable_fields($tosync, [], $this->json->cohorts->update_fields);
                    if (count($data) > 0) {
                        $data['id'] = $ondb->id;
                        \cohort_update_cohort($instance);
                        $ondb = $DB->get_record('cohort', ['id' => $ondb->id]);
                    }
                }
                $this->urls['cohorts'][$tosync->naturalkey] = "$CFG->wwwroot/cohort/edit.php?id=$ondb->id";
                return (object)['on_db' => $ondb, 'to_sync' => $tosync, 'naturalkey' => $tosync->naturalkey];
            }
        );
    }


    /**
     * Synchronizes cohort memberships from the request JSON.
     *
     * @return void
     */
    public function sync_cohorts_members() {
        $this->request_iterator(
            "cohorts_members",
            ["id", "cohortid", "userid", "timeadded"],
            ["cohort_idnumber", "user_username"],
            function ($tosync, $i) {
                \cohort_add_member(
                    $this->get_cached('cohort', 'idnumber', $tosync->cohort_idnumber, true)->id,
                    $this->get_cached('user', 'username', $tosync->user_username, true)->id
                );

                $this->urls['cohorts_members']["$tosync->cohort_idnumber::$tosync->user_username"] = true;
                return true;
            }
        );
    }

    /**
     * Synchronizes course enrolment method instances from the request JSON.
     *
     * @return void
     */
    public function sync_enrols() {
        $this->request_iterator(
            "enrols",
            ['id', 'roleid', 'courseid', 'timecreated', 'timemodified', 'sortorder'],
            ['enrol', 'role_shortname', 'course_idnumber', 'name'],
            function ($tosync, $i) {
                global $CFG, $DB;
                $type = 'enrol';
                $tosync->naturalkey = $tosync->enrol . '::' . $tosync->role_shortname . '::' . $tosync->course_idnumber;

                $role = $this->get_cached('role', 'shortname', $tosync->role_shortname, true);
                $course = $this->get_cached('course', 'idnumber', $tosync->course_idnumber, true);
                $enrolplugin = $this->get_cached_enrol_plugin($tosync->enrol);

                $ondb = $DB->get_record('enrol', ['enrol' => $tosync->enrol, 'courseid' => $course->id, 'roleid' => $role->id]);
                $tosync->op = $ondb ? 'UPD' : 'ADD';

                if ($tosync->op == 'ADD') {
                    $data = (array)$tosync;
                    unset($data['role_shortname']);
                    unset($data['course_idnumber']);
                    unset($data['naturalkey']);
                    unset($data['op']);
                    $data['roleid'] = $role->id;

                    $instanceid = $enrolplugin->add_instance($course, $data);
                    $ondb = $DB->get_record($type, ['id' => $instanceid]);
                    $this->cache[$type][$tosync->naturalkey] = $ondb;
                } else if (isset($this->json->enrols->update_fields)) {
                    $data = $this->set_updatable_fields($tosync, [], $this->json->enrols->update_fields);
                    unset($data['enrol']);
                    unset($data['role_shortname']);
                    unset($data['course_idnumber']);

                    if (count($data) > 0) {
                        $data['id'] = $ondb->id;
                        $enrolplugin->update_instance($ondb, $data);
                        $ondb = $DB->get_record('enrol', ['id' => $ondb->id]);
                        $this->cache[$type][$tosync->naturalkey] = $ondb;
                    }
                }

                $url = "$CFG->wwwroot/enrol/editinstance.php?courseid=$course->id&id=$ondb->id&type=$tosync->enrol";
                $this->urls['enrols'][$tosync->naturalkey] = $url;
                return $ondb;
            }
        );
    }


    /**
     * Synchronizes course groups from the request JSON.
     *
     * @return void
     */
    public function sync_groups() {
        $this->request_iterator(
            "groups",
            ['id', 'courseid', 'timecreated', 'timemodified'],
            ['course_idnumber', 'idnumber', 'name'],
            function ($tosync, $i) {
                global $CFG, $DB;

                $type = 'groups';
                $tosync->naturalkey = $tosync->idnumber . '::' . $tosync->course_idnumber;
                $course = $this->get_cached('course', 'idnumber', $tosync->course_idnumber, true);

                $ondb = $DB->get_record('groups', ['idnumber' => $tosync->idnumber, 'courseid' => $course->id]);
                $tosync->op = $ondb ? 'UPD' : 'ADD';

                if ($tosync->op == 'ADD') {
                    $data = (array)$tosync;
                    unset($data['course_idnumber']);
                    unset($data['naturalkey']);
                    unset($data['op']);
                    $data['courseid'] = $course->id;

                    \groups_create_group((object)$data);

                    $ondb = $DB->get_record($type, ['idnumber' => $tosync->idnumber, 'courseid' => $course->id]);
                    $this->cache[$type][$tosync->naturalkey] = $ondb;
                } else if (isset($this->json->enrols->update_fields)) {
                    $data = $this->set_updatable_fields($tosync, [], $this->json->enrols->update_fields);
                    unset($data['course_idnumber']);

                    if (count($data) > 0) {
                        $data['id'] = $ondb->id;
                        $enrolplugin->update_instance($ondb, $data);
                        $ondb = $DB->get_record('groups', ['id' => $ondb->id]);
                        $this->cache[$type][$tosync->naturalkey] = $ondb;
                    }
                }

                $this->urls[$type][$tosync->naturalkey] = "$CFG->wwwroot/group/group.php?courseid=$course->id&id=$ondb->id";
                return $ondb;
            }
        );
    }


    /**
     * Synchronizes user enrolments from the request JSON.
     *
     * @return void
     */
    public function sync_enrolments() {
        $this->request_iterator(
            "enrolments",
            [
                'id', 'timecreated', 'timemodified', 'modifierid', 'enrolid', 'userid', 'timestart', 'timeend',
                'sortorder', 'itemid', 'contextid', 'roleid',
            ],
            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
            // Opcionais: ['timestart', 'timeend'].
            ['course_idnumber', 'enrol', 'username', 'role_shortname', 'status'],
            function ($tosync, $i) {
                // phpcs:ignore moodle.Commenting.TodoComment.MissingInfoInline
                // TODO: Tratar o caso de timestart e timeend.
                global $CFG, $DB;
                $tosync->naturalkey = "$tosync->username::$tosync->course_idnumber::$tosync->enrol::$tosync->role_shortname";
                $course = $this->get_cached('course', 'idnumber', $tosync->course_idnumber, true);
                $courseenrol = $this->get_course_enrol($tosync->course_idnumber, $tosync->enrol);
                $user = $this->get_cached('user', 'username', $tosync->username, true);
                $role = $this->get_cached('role', 'shortname', $tosync->role_shortname, true);

                if (!is_enrolled($course->context, $user)) {
                    $courseenrol->plugin->enrol_user(
                        $courseenrol->instance,
                        $user->id,
                        $courseenrol->roleid,
                        $tosync->timestart ?? time(),
                        $tosync->timeend ?? 0,
                        $tosync->status
                    );
                } else {
                    $courseenrol->plugin->update_user_enrol(
                        $courseenrol->instance,
                        $user->id,
                        $tosync->status,
                        $tosync->timestart ?? time(),
                        $tosync->timeend ?? 0
                    );
                }
                $this->urls['enrolments'][$tosync->naturalkey] = "$CFG->wwwroot/user/view.php?course=$course->id&id=$user->id";
                return $ondb;
            }
        );
    }


    /**
     * Synchronizes group memberships from the request JSON.
     *
     * @return void
     */
    public function sync_groups_members() {
        $this->request_iterator(
            "groups_members",
            ["id", "groupid", "userid", "timeadded", "componente", "itemid"],
            ['course_idnumber', 'group_idnumber', 'username'],
            function ($tosync, $i) {
                global $CFG, $DB;

                // phpcs:ignore Squiz.PHP.CommentedOutCode.Found, moodle.Commenting.InlineComment.NotCapital
                // \groups_add_member(
                    $this->get_cached_group_course($tosync->group_idnumber, $tosync->course_idnumber)->id;
                // phpcs:ignore Squiz.PHP.CommentedOutCode.Found, moodle.Commenting.InlineComment.NotCapital
                // $this->get_cached('user', 'username', $tosync->username, true)->id
                // );

                $this->urls['groups_members']["$tosync->group_idnumber::$tosync->course_idnumber::$tosync->username"] = true;
                return $ondb;
            }
        );
    }


    /**
     * Records the raw request JSON for later inspection.
     *
     * @param string $jsonstring the raw JSON request body.
     * @return void
     */
    public function insertsyncdb($jsonstring) {
        global $DB;

        $DB->insert_record(
            "sga_enrolment_to_sync",
            (object)[
                'json' => $jsonstring,
                'timecreated' => time(),
                'processed' => 0,
            ]
        );
    }
}

(new sync_up_enrolments_service())->call();

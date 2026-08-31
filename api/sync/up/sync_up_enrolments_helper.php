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
 * Helper methods shared by the SGA enrolment synchronization service.
 *
 * @package     tool_sga
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sga;

/**
 * Helper methods shared by the SGA enrolment synchronization service.
 *
 * @package     tool_sga
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait sync_up_enrolments_helper {
    /** @var \core_course_category[] course categories cached by idnumber. */
    protected $categories = [];
    /** @var \stdClass[] courses cached by idnumber. */
    protected $courses = [];
    /** @var \stdClass[] users cached by username. */
    protected $users = [];
    /** @var \stdClass[] cohorts cached by idnumber. */
    protected $cohorts = [];
    /** @var array generic in-memory cache, indexed by bucket and natural key. */
    protected $cache = [];
    /** @var array enrolments processed so far. */
    protected $enrolments = [];
    /** @var array groups processed so far. */
    protected $groups = [];
    /** @var array default user profile field names allowed to be updated. */
    protected $userfields = [
        'optional' => [
            "confirmed", "policyagreed", "deleted", "suspended", "emailstop",
            "phone1", "phone2", "institution", "department", "address", "city", "country", "lang",
            'timezone', 'idnumber', "password",
        ],
    ];

    /**
     * Decodes and validates the request JSON payload, storing it in $this->json.
     *
     * @param string $jsonstring the raw JSON request body.
     * @return void
     */
    public function validate_json($jsonstring) {

        try {
            $this->json = json_decode($jsonstring);
            if (!$this->json) {
                throw new \Exception("Erro ao validar o JSON.");
            }
        } catch (\Exception $e) {
            throw new \Exception("Erro ao validar o JSON, favor corrigir.");
        }

        if (!is_object($this->json)) {
            throw new \Exception("JSON inválido, favor corrigir.");
        }

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /*
        $schema = json_decode(file_get_contents($CFG->dirroot . '/admin/tool/sga/schemas/sync_up_enrolments.schema.json'));
        $validation = \Jsv4\Validator::validate($this->json, $schema);
        if (!\Jsv4\Validator::isvalid($this->json, $schema)) {
            $errors = "";

            foreach ($validation->errors as $error) {
                $errors .= "{$error->message}";
            }
            throw new \Exception("Erro ao validar o JSON, favor corrigir." . $errors);
        }
        */
    }

    /**
     * Returns a course category by its idnumber, using an in-memory cache.
     *
     * @param string $idnumber the category idnumber.
     * @return \core_course_category|null the category, or null when not found.
     */
    public function get_category_by_idnumber($idnumber) {
        global $DB;
        if (in_array($idnumber, $this->categories)) {
            return $this->categories[$idnumber];
        }

        $cat = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
        if (!$cat) {
            return null;
        }

        $this->categories[$idnumber] = \core_course_category::get($cat->id);
        return $this->categories[$idnumber];
    }

    /**
     * Returns a value from the in-memory cache.
     *
     * @param string $bucket the cache bucket name.
     * @param string $naturalkey the key within the bucket.
     * @param mixed $defaultvalue the value returned when the key is not cached.
     * @return mixed the cached value, or $defaultvalue when not cached.
     */
    public function get_from_cache($bucket, $naturalkey, $defaultvalue = null) {
        if (!array_key_exists($bucket, $this->cache)) {
            $this->cache[$bucket] = [];
        }

        return $this->cache[$bucket][$naturalkey] ?? $defaultvalue;
    }

    /**
     * Stores a value in the in-memory cache.
     *
     * @param string $bucket the cache bucket name.
     * @param string $key the key within the bucket.
     * @param mixed $value the value to store.
     * @return mixed the stored value.
     */
    public function put_into_cache($bucket, $key, $value) {
        if (!array_key_exists($bucket, $this->cache)) {
            $this->cache[$bucket] = [];
        }

        $this->cache[$bucket][$key] = $value;
        return $value;
    }

    /**
     * Returns a database record by a natural key, using an in-memory cache.
     *
     * @param string $origin the database table name.
     * @param string $naturalkey the field used as the natural key.
     * @param mixed $key the natural key value.
     * @param bool $throwsexceptionifnotfound whether to throw when the record is not found.
     * @return \stdClass|null the record, or null when not found and not throwing.
     */
    public function get_cached($origin, $naturalkey, $key, $throwsexceptionifnotfound = false) {
        global $DB;

        $cached = $this->get_from_cache($origin, $key);

        if ($cached) {
            return $cached;
        }

        $ondb = $DB->get_record($origin, [$naturalkey => $key]);
        if (!$ondb) {
            if ($throwsexceptionifnotfound) {
                // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
                throw new \Exception("O registro do tipo '$origin' com `$naturalkey='$key'` não existe, favor corrigir.");
            }
            return null;
        }

        if ($origin === 'course') {
            $ondb->context = \context_course::instance($ondb->id);
        }

        return $this->put_into_cache($origin, $naturalkey, $ondb);
    }

    /**
     * Returns a group by its idnumber within a course, using an in-memory cache.
     *
     * @param string $groupidnumber the group idnumber.
     * @param string $courseidnumber the course idnumber.
     * @return \stdClass the group record.
     */
    public function get_cached_group_course($groupidnumber, $courseidnumber) {
        global $DB;
        $naturalkey = "$groupidnumber::$courseidnumber";
        if ($group = $this->get_from_cache('groups', $naturalkey)) {
            return $group;
        }

        $course = $this->get_cached('course', 'idnumber', $courseidnumber, true);
        $group = $DB->get_record('groups', ['idnumber' => $groupidnumber, 'courseid' => $course->id]);
        $this->put_into_cache('groups', $naturalkey, $group);

        return $group;
    }

    /**
     * Returns an enrolment plugin instance by its type, using an in-memory cache.
     *
     * @param string $enroltype the enrolment plugin type.
     * @return \enrol_plugin the enrolment plugin.
     */
    public function get_cached_enrol_plugin($enroltype) {

        $cached = $this->get_from_cache('enrol_plugins', $enroltype);
        if ($cached) {
            return $cached;
        }

        $enrolplugin = enrol_get_plugin($enroltype);
        if (!$enrolplugin) {
            throw new \Exception("O método de inscrição '{$enroltype}' não existe, favor corrigir.");
        }

        return $this->put_into_cache('enrol_plugins', $enroltype, $enrolplugin);
    }

    /**
     * Returns the enrolment plugin and instance for a course, using an in-memory cache.
     *
     * @param string $courseidnumber the course idnumber.
     * @param string $enroltype the enrolment plugin type.
     * @return \stdClass an object with "plugin", "instance" and "roleid".
     */
    public function get_course_enrol($courseidnumber, $enroltype) {
        $naturalkey = "$enroltype::$courseidnumber";
        $cached = $this->get_from_cache('course_enrol_instances', $naturalkey);

        if ($cached) {
            return $cached;
        }

        $course = $this->get_cached('course', 'idnumber', $courseidnumber);
        $enrolplugin = $this->get_cached_enrol_plugin($enroltype);
        foreach (\enrol_get_instances($course->id, false) as $instance) {
            if ($instance->enrol == $enroltype) {
                $this->put_into_cache(
                    'course_enrol_instances',
                    $naturalkey,
                    (object)['plugin' => $enrolplugin, 'instance' => $instance, 'roleid' => $instance->roleid]
                );
                return $this->get_from_cache('course_enrol_instances', $naturalkey);
            }
        }
        throw new Exception("Não foi encontrado o enrol `$enroltype` para o curso `$courseidnumber`.", 1);
    }

    /**
     * Checks that an object has all the required fields, throwing when one is missing.
     *
     * @param \stdClass $object the object to check.
     * @param string[] $requiredfields the required field names.
     * @param string $objectname the object type name, used in the error message.
     * @param int $objectindexatlist the object's index in the request list, used in the error message.
     * @return void
     */
    public function check_required_fields($object, $requiredfields, $objectname, $objectindexatlist) {
        $error = "";
        foreach ($requiredfields as $requiredfield) {
            if (!property_exists($object, $requiredfield)) {
                $error .= "O $objectname '#{$objectindexatlist}' TEM QUE TER o atributo '$requiredfield', "
                    . "favor corrigir.\n";
            }
        }
        if ($error != "") {
            throw new \Exception($error);
        }
    }

    /**
     * Copies the given updatable fields from an object into a data array.
     *
     * @param \stdClass $object the object to read fields from.
     * @param array $data the data array to update.
     * @param string[] $updatablefields the field names allowed to be updated.
     * @return array the updated data array.
     */
    public function set_updatable_fields($object, $data, $updatablefields) {
        foreach ($updatablefields as $fieldname) {
            if ($fieldname === 'idnumber') {
                throw new \Exception("Não é possível atualizar o 'idnumber'.");
            }

            if (property_exists($object, $fieldname)) {
                $data[$fieldname] = $object->$fieldname;
            }
        }
        return $data;
    }

    /**
     * Checks that an object does not set any banned field, throwing when it does.
     *
     * @param \stdClass $object the object to check.
     * @param string[] $bannedfields the banned field names.
     * @param string $objectname the object type name, used in the error message.
     * @param int $objectindexatlist the object's index in the request list, used in the error message.
     * @return void
     */
    public function check_banned_fields($object, $bannedfields, $objectname, $objectindexatlist) {
        $error = "";

        foreach ($bannedfields as $fieldname) {
            if (isset($object->$fieldname)) {
                $message = "Não é permitido atualizar o atributo '{$fieldname}' do '$objectname' "
                    . "#{$objectindexatlist}, favor corrigir.";
                throw new \Exception($message);
            }
        }
        if ($error != "") {
            throw new \Exception($error);
        }
    }

    /**
     * Resolves the parent category id from its idnumber.
     *
     * @param \stdClass $category the category being synced, with a "parent_idnumber" field.
     * @return int|null the parent category id, or null when not set or not found.
     */
    public function get_parent_id($category) {
        if (!isset($category->parent_idnumber)) {
            return null;
        }
        $parent = $this->get_category_by_idnumber($category->parent_idnumber);
        if (!$parent) {
            return null;
        }
        return $parent->i ?? null;
    }

    /**
     * Returns the first existing course from a course's template_path list.
     *
     * @param \stdClass $course the course being synced, with a "template_path" field.
     * @return \stdClass|null the template course, or null when none is found.
     */
    public function get_template_course($course) {
        $candidatetemplates = array_filter(
            $course->template_path,
            fn($idn) => $this->get_cached('course', 'idnumber', $idn)
        );
        $candidatetemplate = array_values($candidatetemplates)[0] ?? null;
        return $this->get_cached('course', 'idnumber', $candidatetemplate);
    }

    /**
     * Creates a backup of a template course.
     *
     * @param \stdClass $templatecourse the course to back up.
     * @return array|false the backup results, or false when the backup did not finish successfully.
     */
    public function backup_template($templatecourse) {
        // Backup do template.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $templatecourse->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found, moodle.Commenting.InlineComment.NotCapital
            // \backup::MODE_IMPORT,
            get_admin()->id
        );
        $filename = \backup_plan_dbops::get_default_backup_filename(
            $bc->get_format(),
            $bc->get_type(),
            $bc->get_id(),
            false,
            true
        );
        $bc->get_plan()->get_setting('filename')->set_value($filename);
        $bc->execute_plan();
        $result = $bc->get_results();
        $bc->destroy();
        return ($bc->get_status() == \backup::STATUS_FINISHED_OK) ? $result : false;
    }

    /**
     * Restores a backup file into a course.
     *
     * @param \stored_file $backupfile the backup file to restore.
     * @param \stdClass $course the destination course.
     * @return bool true on success.
     */
    public function restore_into_course($backupfile, $course) {
        $backupdir = \restore_controller::get_tempdir_name(SITEID, get_admin()->id);
        $path = make_backup_temp_directory($backupdir);
        $backupfile->copy_content_to("$path/kkk");
        $fp = get_file_packer('application/vnd.moodle.backup');
        $fp->extract_to_pathname("$path/kkk", $path);

        $rc = new \restore_controller(
            $backupdir,
            $course->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            get_admin()->id,
            \backup::TARGET_EXISTING_ADDING
        );

        if ($rc->execute_precheck()) {
            try {
                $rc->execute_plan();
                return true;
            } catch (\Throwable $th) {
                return false;
            }
        } else {
            return false;
        }
        $rc->destroy();
    }

    /**
     * Iterates over a list of objects in the request JSON, validating and processing each one.
     *
     * @param string $type the JSON property holding the list (e.g. "courses", "users").
     * @param string[] $bannedfields fields the request objects must not set.
     * @param string[] $requiredfields fields the request objects must set.
     * @param callable $callback callback invoked for each object as ($object, $index).
     * @return void
     */
    public function request_iterator($type, $bannedfields, $requiredfields, $callback) {
        global $CFG;

        $haslist = isset($this->json->$type)
            && is_object($this->json->$type)
            && isset($this->json->$type->list)
            && is_array($this->json->$type->list);
        if (!$haslist) {
            return;
        }

        $i = 0;
        $this->$type = [];
        foreach ($this->json->$type->list ?? [] as $object) {
            $naturalkey = $object->username ?? $object->idnumber ?? 'unknown';
            try {
                $this->check_banned_fields($object, $bannedfields, $type, $i);
                $this->check_required_fields($object, $requiredfields, $type, $i);

                $ondb = $callback($object, $i);
                $this->$type[$naturalkey] = $ondb;
            } catch (\Throwable $th) {
                $this->urls[$type][$naturalkey] = null;
                $op = isset($object->new) && $object->new ? 'ADD' : 'UPD';
                // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
                $message = "Erro ao processar($op) o $type#$i '{$naturalkey}': `" . $th->getMessage() . "`.";
                $this->errors[] = $message;
            }
            $i++;
        }
    }

    /**
     * Parses a date value, accepting a unix timestamp or an ISO date string.
     *
     * @param mixed $date the date value to parse.
     * @param string $attname the attribute name, used in error messages.
     * @param int $dafeultvalue the value returned when $date is not set.
     * @return int the parsed unix timestamp.
     */
    public function parse_date($date, $attname, $dafeultvalue = 0) {
        if (isset($date)) {
            if (is_string($date)) {
                if (strtotime($date) === false) {
                    $message = "O atributo '$attname' deve ser uma string com data válida em formato ISO, "
                        . "favor corrigir.";
                    throw new \Exception($message);
                }
                return strtotime($date);
            } else if (is_int($tosync->timestart)) {
                if ($date < 0) {
                    throw new \Exception("O atributo '$attname' deve ser um unix timestamp positivo, favor corrigir.");
                }
                return $date;
            } else {
                $message = "O atributo '$attname' deve ser um inteiro representando um unix timestamp ou uma "
                    . "string com data válida em formato ISO, favor corrigir.";
                throw new \Exception($message);
            }
        }
        return $dafeultvalue;
    }
}

<?php
// This file is part of "Moodle SGA Integration"
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
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sga;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/course/externallib.php");
require_once("$CFG->dirroot/enrol/externallib.php");
require_once("$CFG->dirroot/message/externallib.php");
require_once("$CFG->dirroot/message/output/popup/externallib.php");

/**
 * Returns the next sort order value for a table.
 *
 * @param string $tablename the table name, without the Moodle prefix or braces.
 * @return int the next available sort order value.
 */
function get_last_sort_order($tablename) {
    global $DB;
    $l = $DB->get_record_sql('SELECT coalesce(max(sortorder), 0) + 1 as sortorder from {' . $tablename . '}');
    return $l->sortorder;
}

/**
 * Fetches a record matching the given keys, creating it if it does not exist.
 *
 * @param string $tablename the table name, without the Moodle prefix or braces.
 * @param array $keys fields used to look up the existing record.
 * @param array $values additional fields used when creating the record.
 * @return \stdClass the existing or newly created record.
 */
function get_or_create($tablename, $keys, $values) {
    global $DB;
    $record = $DB->get_record($tablename, $keys);
    if (!$record) {
        $record = (object)array_merge($keys, $values);
        $record->id = $DB->insert_record($tablename, $record);
    }
    return $record;
}

/**
 * Updates a record matching the given keys, or inserts a new one if none is found.
 *
 * @param string $tablename the table name, without the Moodle prefix or braces.
 * @param array $keys fields used to look up the existing record.
 * @param array $allways fields applied both on update and on insert.
 * @param array $updates additional fields applied only on update.
 * @param array $insert additional fields applied only on insert.
 * @return \stdClass the updated or newly created record.
 */
function create_or_update($tablename, $keys, $allways, $updates = [], $insert = []) {
    global $DB;
    $record = $DB->get_record($tablename, $keys);
    if ($record) {
        foreach (array_merge($keys, $allways, $updates) as $attr => $value) {
            $record->{$attr} = $value;
        }
        $DB->update_record($tablename, $record);
    } else {
        $record = (object)array_merge($keys, $allways, $insert);
        $record->id = $DB->insert_record($tablename, $record);
    }
    return $record;
}

/**
 * Terminates the request with a JSON error payload.
 *
 * @param string $message the error message.
 * @param int $code the HTTP status code.
 * @return void
 */
function dienow($message, $code) {
    http_response_code($code);
    die(json_encode(["message" => $message, "code" => $code]));
}

/**
 * Returns a tool_sga plugin configuration value.
 *
 * @param string $name the configuration key.
 * @return mixed the configuration value.
 */
function config($name) {
    return get_config('tool_sga', $name);
}

/**
 * Returns an array value for a key, or a default when it is not set.
 *
 * @param array $array the array to look up.
 * @param string $key the key to look up.
 * @param mixed $default the value returned when the key is not set.
 * @return mixed the array value or the default.
 */
function aget($array, $key, $default = null) {
    return \key_exists($key, $array) ? $array[$key] : $default;
}

/**
 * Runs a SQL query and returns the resulting recordset encoded as a JSON array.
 *
 * @param string $sql the SQL query to run.
 * @param array $params the query parameters.
 * @return string the recordset encoded as a JSON array.
 */
function get_recordset_as_json($sql, $params) {
    global $DB;

    $result = "[";
    $sep = '';
    foreach ($DB->get_recordset_sql($sql, $params) as $disciplina) {
        $result .= $sep . json_encode($disciplina);
        $sep = ',';
    }
    return $result . "]";
}

/**
 * Runs a SQL query and returns the resulting recordset as an array.
 *
 * @param string $sql the SQL query to run.
 * @param array $params the query parameters.
 * @return array the recordset rows.
 */
function get_recordset_as_array($sql, $params) {
    global $DB;

    $result = [];
    foreach ($DB->get_recordset_sql($sql, $params) as $disciplina) {
        $result[] = $disciplina;
    }
    return $result;
}

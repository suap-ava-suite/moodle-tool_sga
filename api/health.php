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
 * SUAP Integration - Health check service
 *
 * Validates the authentication token without performing any side effects.
 * Returns 200 OK when the token is valid, 401 Unauthorized otherwise.
 *
 * @package     local_suap
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sga;
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
 *
 * This file implements a health check service that validates the authentication token without performing any side effects.
 * It returns 200 OK when the token is valid, and 401 Unauthorized otherwise.
 */

class health_service extends service
{
    function do_call() {
        global $CFG;
        $plugin = new \stdClass();
        require($CFG->dirroot . '/admin/tool/sga/version.php');
        return [
            "status"          => "ok",
            "moodle_version"  => $CFG->version,
            "moodle_release" => $CFG->release,
            "plugin_version"  => $plugin->version,
            "plugin_release"  => $plugin->release,
        ];
    }
}

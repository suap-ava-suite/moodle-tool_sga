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

defined('MOODLE_INTERNAL') || die();

/**
 * Reports an exception as a JSON error response and terminates the request.
 *
 * HTTP response codes:
 * 200 – 208, 226,
 * 300 – 305, 307, 308
 * 400 – 417, 422 – 424, 426, 428 – 429, 431
 * 500 – 508, 510 – 511
 *
 * @param \Exception $exception the exception to report.
 * @return void
 */
function exception_handler($exception) {
    $errorcode = $exception->getCode() ?: 500;
    http_response_code($errorcode);
    die(json_encode(["error" => ["message" => $exception->getMessage() . " " . $errorcode, "code" => $errorcode]]));
}

/**
 * Base class for tool_sga API services.
 *
 * @package     tool_sga
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service {
    /**
     * Validates the "Authentication" header against the configured token.
     *
     * @return void
     */
    public function authenticate() {
        $syncupauthtoken = config('integration_token');

        $headers = getallheaders();
        $authenticationkey = array_key_exists('Authentication', $headers) ? "Authentication" : "authentication";
        if (!array_key_exists($authenticationkey, $headers)) {
            throw new \Exception("Bad Request - Authentication not informed", 400);
        }

        if ("Token $syncupauthtoken" != $headers[$authenticationkey]) {
            throw new \Exception("Unauthorized", 401);
        }
    }

    /**
     * Authenticates the request, runs the service and outputs the JSON response.
     *
     * @return void
     */
    public function call() {
        $this->authenticate();
        $data = $this->do_call();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Runs the service. Must be overridden by subclasses.
     *
     * @return mixed the service result.
     */
    public function do_call() {
        throw new \Exception("Não implementado", 501);
    }
}


try {
    header('Content-Type: application/json; charset=utf-8');
    set_exception_handler('\tool_sga\exception_handler');

    $whitelist = [
        'sync_up_enrolments',
        'sync_down_grades',
        'health',
    ];
    $params = explode('&', $_SERVER["QUERY_STRING"]);
    $servicename = $params[0];

    if ((!in_array($servicename, $whitelist))) {
        throw new \Exception("Serviço não existe", 404);
    }
    require_once("$servicename.php");

    $serviceclass = "\\tool_sga\\$servicename" . "_service";
    $service = new $serviceclass();
    $service->call();
} catch (\Exception $e) {
    /*
        200 – 208, 226,
        300 – 305, 307, 308
        400 – 417, 422 – 424, 426, 428 – 429, 431
        500 – 508, 510 – 511
    */
    exception_handler($e);
}

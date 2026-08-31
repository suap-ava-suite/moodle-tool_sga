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
 * JSON Schema (draft v4) validation exception.
 *
 * @package     tool_sga
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Jsv4;

/**
 * Exception raised when a value fails JSON Schema validation.
 *
 * @package     tool_sga
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ValidationException extends \RuntimeException
{
    /** @var int the Validator::* error code. */
    public $code;
    /** @var string the JSON pointer path to the invalid data. */
    public $datapath;
    /** @var string the JSON pointer path to the schema rule that failed. */
    public $schemapath;
    /** @var string the error message. */
    public $message;

    /**
     * Constructs the exception.
     *
     * @param int $code the Validator::* error code.
     * @param string $datapath the JSON pointer path to the invalid data.
     * @param string $schemapath the JSON pointer path to the schema rule that failed.
     * @param string $errormessage the error message.
     * @param \Jsv4\Validator[]|null $subresults sub-validation results, when applicable.
     */
    public function __construct($code, $datapath, $schemapath, $errormessage, $subresults = null) {
        parent::__construct($errormessage);
        $this->code          = $code;
        $this->datapath      = $datapath;
        $this->schemapath    = $schemapath;
        $this->message       = $errormessage;
        if ($subresults) {
            $this->subresults = $subresults;
        }
    }

    /**
     * Returns a copy of this exception with the data and schema paths prefixed.
     *
     * @param string $dataprefix the prefix to prepend to the data path.
     * @param string $schemaprefix the prefix to prepend to the schema path.
     * @return ValidationException the prefixed exception.
     */
    public function prefix($dataprefix, $schemaprefix) {
        return new ValidationException(
            $this->code,
            $dataprefix . $this->datapath,
            $schemaprefix . $this->schemapath,
            $this->message
        );
    }
}

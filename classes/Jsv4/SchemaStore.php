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

namespace Jsv4;

/**
 * Stores and resolves JSON Schema (draft v4) documents by URL, including $ref resolution.
 *
 * @package     tool_sga
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class SchemaStore
{
    /**
     * Resolves a JSON pointer path within a value.
     *
     * @param mixed $value the value to look up, passed by reference.
     * @param string $path the JSON pointer path.
     * @param bool $strict whether to throw when the path does not exist.
     * @return mixed the resolved value, or null when not found and not strict.
     */
    private static function pointerget(&$value, $path = "", $strict = false) {
        if ($path == "") {
            return $value;
        } else if ($path[0] != "/") {
            throw new Exception("Invalid path: $path");
        }
        $parts = explode("/", $path);
        array_shift($parts);
        foreach ($parts as $part) {
            $part    = str_replace("~1", "/", $part);
            $part    = str_replace("~0", "~", $part);
            if (is_array($value) && is_numeric($part)) {
                $value = & $value[$part];
            } else if (is_object($value)) {
                if (isset($value->$part)) {
                    $value = & $value->$part;
                } else if ($strict) {
                    throw new Exception("Path does not exist: $path");
                } else {
                    return null;
                }
            } else if ($strict) {
                throw new Exception("Path does not exist: $path");
            } else {
                return null;
            }
        }
        return $value;
    }


    /**
     * Checks whether an array is a sequential numeric array.
     *
     * @param array $array the array to check.
     * @return bool true when the array is sequential.
     */
    private static function isnumericarray($array) {
        $count = count($array);
        for ($i = 0; $i < $count; $i++) {
            if (!isset($array[$i])) {
                return false;
            }
        }
        return true;
    }


    /**
     * Resolves a relative URL against a base URL.
     *
     * @param string $base the base URL.
     * @param string $relative the relative URL.
     * @return string the resolved absolute URL.
     */
    private static function resolveurl($base, $relative) {
        if (parse_url($relative, PHP_URL_SCHEME) != '') {
            // It's already absolute.
            return $relative;
        }
        $baseparts = parse_url($base);
        if ($relative[0] == "?") {
            $baseparts['query'] = substr($relative, 1);
            unset($baseparts['fragment']);
        } else if ($relative[0] == "#") {
            $baseparts['fragment'] = substr($relative, 1);
        } else if ($relative[0] == "/") {
            if ($relative[1] == "/") {
                return $baseparts['scheme'] . $relative;
            }
            $baseparts['path'] = $relative;
            unset($baseparts['query']);
            unset($baseparts['fragment']);
        } else {
            $basepathparts       = explode("/", $baseparts['path']);
            $relativepathparts   = explode("/", $relative);
            array_pop($basepathparts);
            while (count($relativepathparts)) {
                if ($relativepathparts[0] == "..") {
                    array_shift($relativepathparts);
                    if (count($basepathparts)) {
                        array_pop($basepathparts);
                    }
                } else if ($relativepathparts[0] == ".") {
                    array_shift($relativepathparts);
                } else {
                    array_push($basepathparts, array_shift($relativepathparts));
                }
            }
            $baseparts['path'] = implode("/", $basepathparts);
            if ($baseparts['path'][0] != '/') {
                $baseparts['path'] = "/" . $baseparts['path'];
            }
        }

        $result = "";
        if (isset($baseparts['scheme'])) {
            $result .= $baseparts['scheme'] . "://";
            if (isset($baseparts['user'])) {
                $result .= ":" . $baseparts['user'];
                if (isset($baseparts['pass'])) {
                    $result .= ":" . $baseparts['pass'];
                }
                $result .= "@";
            }
            $result .= $baseparts['host'];
            if (isset($baseparts['port'])) {
                $result .= ":" . $baseparts['port'];
            }
        }
        $result .= $baseparts["path"];
        if (isset($baseparts['query'])) {
            $result .= "?" . $baseparts['query'];
        }
        if (isset($baseparts['fragment'])) {
            $result .= "#" . $baseparts['fragment'];
        }
        return $result;
    }


    /** @var array<string, mixed> schemas indexed by their resolved URL. */
    private $schemas = [];
    /** @var array<string, array> pending unresolved $ref schemas, indexed by base URL. */
    private $refs    = [];

    /**
     * Returns the list of base URLs with unresolved schema references.
     *
     * @return string[] the base URLs still missing.
     */
    public function missing() {
        return array_keys($this->refs);
    }

    /**
     * Adds a schema to the store, normalizing and resolving its references.
     *
     * @param string $url the schema URL.
     * @param mixed $schema the schema, passed by reference.
     * @param bool $trusted whether nested schema ids should be trusted regardless of prefix.
     * @return void
     */
    public function add($url, $schema, $trusted = false) {
        $urlparts    = explode("#", $url);
        $baseurl     = array_shift($urlparts);
        $fragment    = urldecode(implode("#", $urlparts));

        $trustbase   = explode("?", $baseurl);
        $trustbase   = $trustbase[0];

        $this->schemas[$url] = & $schema;
        $this->normalizeschema($url, $schema, $trusted ? true : $trustbase);
        if ($fragment == "") {
            $this->schemas[$baseurl] = $schema;
        }
        if (isset($this->refs[$baseurl])) {
            foreach ($this->refs[$baseurl] as $fullurl => $refschemas) {
                foreach ($refschemas as &$refschema) {
                    $refschema = $this->get($fullurl);
                }
                unset($this->refs[$baseurl][$fullurl]);
            }
            if (isset($this->refs[$baseurl]) && count($this->refs[$baseurl]) === 0) {
                unset($this->refs[$baseurl]);
            }
        }
    }


    /**
     * Recursively normalizes a schema, resolving "id" and "$ref" against the base URL.
     *
     * @param string $url the base URL used to resolve relative references.
     * @param mixed $schema the schema, passed by reference.
     * @param string|bool $trustprefix URL prefix trusted for embedded schema ids, or true to trust all.
     * @return void
     */
    private function normalizeschema($url, &$schema, $trustprefix = '') {
        if (is_array($schema) && !self::isnumericarray($schema)) {
            $schema = (object) $schema;
        }
        if (is_object($schema)) {
            if (isset($schema->{'$ref'})) {
                $refurl              = $schema->{'$ref'}     = self::resolveurl($url, $schema->{'$ref'});
                if ($refschema           = $this->get($refurl)) {
                    $schema = $refschema;
                    return;
                } else {
                    $urlparts                        = explode("#", $refurl);
                    $baseurl                         = array_shift($urlparts);
                    $fragment                        = urldecode(implode("#", $urlparts));
                    $this->refs[$baseurl][$refurl][] = & $schema;
                }
            } else if (isset($schema->id) && is_string($schema->id)) {
                $schema->id  = $url      = self::resolveurl($url, $schema->id);
                $regex       = '/^' . preg_quote($trustprefix, '/') . '(?:[#\/?].*)?$/';
                if (($trustprefix === true || preg_match($regex, $schema->id)) && !isset($this->schemas[$schema->id])) {
                    $this->add($schema->id, $schema);
                }
            }
            foreach ($schema as $key => &$value) {
                if ($key != "enum") {
                    self::normalizeschema($url, $value, $trustprefix);
                }
            }
        } else if (is_array($schema)) {
            foreach ($schema as &$value) {
                self::normalizeschema($url, $value, $trustprefix);
            }
        }
    }


    /**
     * Returns a stored schema, or a fragment resolved from it via JSON pointer.
     *
     * @param string $url the schema URL, optionally with a "#/json/pointer" fragment.
     * @return mixed the resolved schema, or null when not found.
     */
    public function get($url) {
        if (isset($this->schemas[$url])) {
            return $this->schemas[$url];
        }
        $urlparts    = explode("#", $url);
        $baseurl     = array_shift($urlparts);
        $fragment    = urldecode(implode("#", $urlparts));
        if (isset($this->schemas[$baseurl])) {
            $schema = $this->schemas[$baseurl];
            if ($schema && $fragment == "" || $fragment[0] == "/") {
                $schema = self::pointerget($schema, $fragment);
                $this->add($url, $schema);
                return $schema;
            }
        }
    }
}

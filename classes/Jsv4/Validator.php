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
 * JSON Schema (draft v4) validator.
 *
 * @package     tool_sga
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Jsv4;

/**
 * Validates a value against a JSON Schema (draft v4) document.
 *
 * @package     tool_sga
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Validator
{
    /** @var int the value does not match any of the schema's allowed types. */
    const INVALID_TYPE               = 0;
    /** @var int the value does not match any of the schema's enum options. */
    const ENUM_MISMATCH              = 1;
    /** @var int the value does not satisfy any of the schemas in "anyOf". */
    const ANY_OF_MISSING                 = 10;
    /** @var int the value does not satisfy any of the schemas in "oneOf". */
    const ONE_OF_MISSING                 = 11;
    /** @var int the value satisfies more than one of the schemas in "oneOf". */
    const ONE_OF_MULTIPLE                = 12;
    /** @var int the value satisfies the schema in "not", which is prohibited. */
    const NOT_PASSED                     = 13;

    // Numeric errors.
    /** @var int the number is not a multiple of "multipleOf". */
    const NUMBER_MULTIPLE_OF             = 100;
    /** @var int the number is below "minimum". */
    const NUMBER_MINIMUM                 = 101;
    /** @var int the number is below or equal to an exclusive "minimum". */
    const NUMBER_MINIMUM_EXCLUSIVE   = 102;
    /** @var int the number is above "maximum". */
    const NUMBER_MAXIMUM                 = 103;
    /** @var int the number is above or equal to an exclusive "maximum". */
    const NUMBER_MAXIMUM_EXCLUSIVE   = 104;

    // String errors.
    /** @var int the string is shorter than "minLength". */
    const STRING_LENGTH_SHORT            = 200;
    /** @var int the string is longer than "maxLength". */
    const STRING_LENGTH_LONG             = 201;
    /** @var int the string does not match "pattern". */
    const STRING_PATTERN                 = 202;

    // Object errors.
    /** @var int the object has fewer properties than "minProperties". */
    const OBJECT_PROPERTIES_MINIMUM  = 300;
    /** @var int the object has more properties than "maxProperties". */
    const OBJECT_PROPERTIES_MAXIMUM  = 301;
    /** @var int the object is missing a property listed in "required". */
    const OBJECT_REQUIRED                = 302;
    /** @var int the object has a property not allowed by "additionalProperties". */
    const OBJECT_ADDITIONAL_PROPERTIES = 303;
    /** @var int the object is missing a property required by "dependencies". */
    const OBJECT_DEPENDENCY_KEY      = 304;

    // Array errors.
    /** @var int the array has fewer items than "minItems". */
    const ARRAY_LENGTH_SHORT             = 400;
    /** @var int the array has more items than "maxItems". */
    const ARRAY_LENGTH_LONG          = 401;
    /** @var int the array has duplicate items, but "uniqueItems" requires them unique. */
    const ARRAY_UNIQUE               = 402;
    /** @var int the array has an item not allowed by "additionalItems". */
    const ARRAY_ADDITIONAL_ITEMS         = 403;

    /** @var mixed the data being validated. */
    private $data;
    /** @var mixed the schema being validated against. */
    private $schema;
    /** @var bool whether to stop at the first validation error. */
    private $firsterroronly;
    /** @var bool whether to coerce the data to match the schema's type. */
    private $coerce;
    /** @var bool whether the data is valid according to the schema. */
    public $valid;
    /** @var ValidationException[] the validation errors found. */
    public $errors;

    /**
     * Validates data against a schema.
     *
     * @param mixed $data the data to validate, passed by reference.
     * @param mixed $schema the schema to validate against.
     * @param bool $firsterroronly whether to stop at the first validation error.
     * @param bool $coerce whether to coerce the data to match the schema's type.
     */
    private function __construct(&$data, $schema, $firsterroronly = false, $coerce = false) {
        $this->data              = & $data;
        $this->schema            = & $schema;
        $this->firsterroronly    = $firsterroronly;
        $this->coerce            = $coerce;
        $this->valid             = true;
        $this->errors            = [];

        try {
            $this->checktypes();
            $this->checkenum();
            $this->checkobject();
            $this->checkarray();
            $this->checkstring();
            $this->checknumber();
            $this->checkcomposite();
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (ValidationException $e) {
            // First-error-only mode: checks above throw once $this->errors has the single failure to report.
        }
    }

    /**
     * Validates data against a schema, collecting all errors found.
     *
     * @param mixed $data the data to validate.
     * @param mixed $schema the schema to validate against.
     * @return Validator the validation result.
     */
    public static function validate($data, $schema) {
        return new Validator($data, $schema);
    }

    /**
     * Checks whether data is valid against a schema, stopping at the first error.
     *
     * @param mixed $data the data to validate.
     * @param mixed $schema the schema to validate against.
     * @return bool true when the data is valid.
     */
    public static function isvalid($data, $schema) {
        $result = new Validator($data, $schema, true);
        return $result->valid;
    }


    /**
     * Validates data against a schema, coercing the data to match the schema's type when possible.
     *
     * @param mixed $data the data to validate.
     * @param mixed $schema the schema to validate against.
     * @return Validator the validation result, with "value" set to the coerced data when valid.
     */
    public static function coerce($data, $schema) {
        if (is_object($data) || is_array($data)) {
            $data = unserialize(serialize($data));
        }
        $result = new Validator($data, $schema, false, true);
        if ($result->valid) {
            $result->value = $result->data;
        }
        return $result;
    }

    /**
     * Joins JSON pointer path parts into a single escaped pointer string.
     *
     * @param string[] $parts the path parts.
     * @return string the joined JSON pointer.
     */
    public static function pointerjoin($parts) {
        $result = "";
        foreach ($parts as $part) {
            $part    = str_replace("~", "~0", $part);
            $part    = str_replace("/", "~1", $part);
            $result .= "/" . $part;
        }
        return $result;
    }

    /**
     * Recursively compares two values for deep equality.
     *
     * @param mixed $a the first value.
     * @param mixed $b the second value.
     * @return bool true when the values are deeply equal.
     */
    public static function recursiveequal($a, $b) {
        if (is_object($a)) {
            if (!is_object($b)) {
                return false;
            }
            foreach ($a as $key => $value) {
                if (!isset($b->$key)) {
                    return false;
                }
                if (!self::recursiveequal($value, $b->$key)) {
                    return false;
                }
            }
            foreach ($b as $key => $value) {
                if (!isset($a->$key)) {
                    return false;
                }
            }
            return true;
        }
        if (is_array($a)) {
            if (!is_array($b)) {
                return false;
            }
            foreach ($a as $key => $value) {
                if (!isset($b[$key])) {
                    return false;
                }
                if (!self::recursiveequal($value, $b[$key])) {
                    return false;
                }
            }
            foreach ($b as $key => $value) {
                if (!isset($a[$key])) {
                    return false;
                }
            }
            return true;
        }
        return $a === $b;
    }


    /**
     * Records a validation failure.
     *
     * @param int $code the Validator::* error code.
     * @param string $datapath the JSON pointer path to the invalid data.
     * @param string $schemapath the JSON pointer path to the schema rule that failed.
     * @param string $errormessage the error message.
     * @param Validator[]|null $suberrors sub-validation results, when applicable.
     * @return void
     */
    private function fail($code, $datapath, $schemapath, $errormessage, $suberrors = null) {
        $this->valid     = false;
        $error           = new ValidationException($code, $datapath, $schemapath, $errormessage, $suberrors);
        $this->errors[]  = $error;
        if ($this->firsterroronly) {
            throw $error;
        }
    }

    /**
     * Validates a nested value against a nested schema.
     *
     * @param mixed $data the nested data, passed by reference.
     * @param mixed $schema the nested schema.
     * @param bool $allowcoercion whether to allow coercion for this sub-result.
     * @return Validator the sub-validation result.
     */
    private function subresult(&$data, $schema, $allowcoercion = true) {
        return new Validator($data, $schema, $this->firsterroronly, $allowcoercion && $this->coerce);
    }

    /**
     * Merges the errors of a sub-validation result into this result.
     *
     * @param Validator $subresult the sub-validation result.
     * @param string $dataprefix the prefix to prepend to the sub-result's error data paths.
     * @param string $schemaprefix the prefix to prepend to the sub-result's error schema paths.
     * @return void
     */
    private function includesubresult($subresult, $dataprefix, $schemaprefix) {
        if (!$subresult->valid) {
            $this->valid = false;
            foreach ($subresult->errors as $error) {
                $this->errors[] = $error->prefix($dataprefix, $schemaprefix);
            }
        }
    }


    /**
     * Validates the data against the schema's "type" keyword, coercing it when enabled.
     *
     * @return void
     */
    private function checktypes() {
        if (isset($this->schema->type)) {
            $types = $this->schema->type;
            if (!is_array($types)) {
                $types = [$types];
            }
            foreach ($types as $type) {
                if ($type == "object" && is_object($this->data)) {
                    return;
                } else if ($type == "array" && is_array($this->data)) {
                    return;
                } else if ($type == "string" && is_string($this->data)) {
                    return;
                } else if ($type == "number" && !is_string($this->data) && is_numeric($this->data)) {
                    return;
                } else if ($type == "integer" && is_int($this->data)) {
                    return;
                } else if ($type == "boolean" && is_bool($this->data)) {
                    return;
                } else if ($type == "null" && $this->data === null) {
                    return;
                }
            }

            if ($this->coerce) {
                foreach ($types as $type) {
                    if ($type == "number") {
                        if (is_numeric($this->data)) {
                            $this->data = (float) $this->data;
                            return;
                        } else if (is_bool($this->data)) {
                            $this->data = $this->data ? 1 : 0;
                            return;
                        }
                    } else if ($type == "integer") {
                        if ((int) $this->data == $this->data) {
                            $this->data = (int) $this->data;
                            return;
                        }
                    } else if ($type == "string") {
                        if (is_numeric($this->data)) {
                            $this->data = "" . $this->data;
                            return;
                        } else if (is_bool($this->data)) {
                            $this->data = ($this->data) ? "true" : "false";
                            return;
                        } else if (is_null($this->data)) {
                            $this->data = "";
                            return;
                        }
                    } else if ($type == "boolean") {
                        if (is_numeric($this->data)) {
                            $this->data = ($this->data != "0");
                            return;
                        } else if ($this->data == "yes" || $this->data == "true") {
                            $this->data = true;
                            return;
                        } else if ($this->data == "no" || $this->data == "false") {
                            $this->data = false;
                            return;
                        } else if ($this->data == null) {
                            $this->data = false;
                            return;
                        }
                    }
                }
            }

            $type = gettype($this->data);
            if ($type == "double") {
                $type = ((int) $this->data == $this->data) ? "integer" : "number";
            } else if ($type == "NULL") {
                $type = "null";
            }
            $this->fail(self::INVALID_TYPE, "", "/type", "Invalid type: $type");
        }
    }


    /**
     * Validates the data against the schema's "enum" keyword.
     *
     * @return void
     */
    private function checkenum() {
        if (isset($this->schema->enum)) {
            foreach ($this->schema->enum as $option) {
                if (self::recursiveequal($this->data, $option)) {
                    return;
                }
            }
            $this->fail(self::ENUM_MISMATCH, "", "/enum", "Value must be one of the enum options");
        }
    }


    /**
     * Validates the data against the schema's object-related keywords (required, properties,
     * patternProperties, additionalProperties, dependencies, minProperties, maxProperties).
     *
     * @return void
     */
    private function checkobject() {
        if (!is_object($this->data)) {
            return;
        }
        if (isset($this->schema->required)) {
            foreach ($this->schema->required as $index => $key) {
                if (!array_key_exists($key, (array) $this->data)) {
                    if ($this->coerce && $this->createvalueforproperty($key)) {
                        continue;
                    }
                    $this->fail(self::OBJECT_REQUIRED, "", "/required/{$index}", "Missing required property: {$key}");
                }
            }
        }
        $checkedproperties = [];
        if (isset($this->schema->properties)) {
            foreach ($this->schema->properties as $key => $subschema) {
                $checkedproperties[$key] = true;
                if (array_key_exists($key, (array) $this->data)) {
                    $subresult = $this->subresult($this->data->$key, $subschema);
                    $this->includesubresult($subresult, self::pointerjoin([$key]), self::pointerjoin(["properties", $key]));
                }
            }
        }
        if (isset($this->schema->patternProperties)) {
            foreach ($this->schema->patternProperties as $pattern => $subschema) {
                foreach ($this->data as $key => &$subvalue) {
                    if (preg_match("/" . str_replace("/", "\\/", $pattern) . "/", $key)) {
                        $checkedproperties[$key] = true;
                        $subresult               = $this->subresult($this->data->$key, $subschema);
                        $this->includesubresult(
                            $subresult,
                            self::pointerjoin([$key]),
                            self::pointerjoin(["patternProperties", $pattern])
                        );
                    }
                }
            }
        }
        if (isset($this->schema->additionalProperties)) {
            $additionalproperties = $this->schema->additionalProperties;
            foreach ($this->data as $key => &$subvalue) {
                if (isset($checkedproperties[$key])) {
                    continue;
                }
                if (!$additionalproperties) {
                    $this->fail(
                        self::OBJECT_ADDITIONAL_PROPERTIES,
                        self::pointerjoin([$key]),
                        "/additionalProperties",
                        "Additional properties not allowed"
                    );
                } else if (is_object($additionalproperties)) {
                    $subresult = $this->subresult($subvalue, $additionalproperties);
                    $this->includesubresult($subresult, self::pointerjoin([$key]), "/additionalProperties");
                }
            }
        }
        if (isset($this->schema->dependencies)) {
            foreach ($this->schema->dependencies as $key => $dep) {
                if (!isset($this->data->$key)) {
                    continue;
                }
                if (is_object($dep)) {
                    $subresult = $this->subresult($this->data, $dep);
                    $this->includesubresult($subresult, "", self::pointerjoin(["dependencies", $key]));
                } else if (is_array($dep)) {
                    foreach ($dep as $index => $depkey) {
                        if (!isset($this->data->$depkey)) {
                            $this->fail(
                                self::OBJECT_DEPENDENCY_KEY,
                                "",
                                self::pointerjoin(["dependencies", $key, $index]),
                                "Property $key depends on $depkey"
                            );
                        }
                    }
                } else {
                    if (!isset($this->data->$dep)) {
                        $this->fail(
                            self::OBJECT_DEPENDENCY_KEY,
                            "",
                            self::pointerjoin(["dependencies", $key]),
                            "Property $key depends on $dep"
                        );
                    }
                }
            }
        }
        if (isset($this->schema->minProperties)) {
            if (count(get_object_vars($this->data)) < $this->schema->minProperties) {
                $message = ($this->schema->minProperties == 1)
                    ? "Object cannot be empty"
                    : "Object must have at least {$this->schema->minProperties} defined properties";
                $this->fail(self::OBJECT_PROPERTIES_MINIMUM, "", "/minProperties", $message);
            }
        }
        if (isset($this->schema->maxProperties)) {
            if (count(get_object_vars($this->data)) > $this->schema->maxProperties) {
                $message = ($this->schema->maxProperties == 1)
                    ? "Object must have at most one defined property"
                    : "Object must have at most {$this->schema->maxProperties} defined properties";
                $this->fail(self::OBJECT_PROPERTIES_MAXIMUM, "", "/minProperties", $message);
            }
        }
    }


    /**
     * Validates the data against the schema's array-related keywords (items, additionalItems,
     * minItems, maxItems, uniqueItems).
     *
     * @return void
     */
    private function checkarray() {
        if (!is_array($this->data)) {
            return;
        }
        if (isset($this->schema->items)) {
            $items = $this->schema->items;
            if (is_array($items)) {
                foreach ($this->data as $index => &$subdata) {
                    if (!is_numeric($index)) {
                        throw new Exception("Arrays must only be numerically-indexed");
                    }
                    if (isset($items[$index])) {
                        $subresult = $this->subresult($subdata, $items[$index]);
                        $this->includesubresult($subresult, "/{$index}", "/items/{$index}");
                    } else if (isset($this->schema->additionalItems)) {
                        $additionalitems = $this->schema->additionalItems;
                        if (!$additionalitems) {
                            $this->fail(
                                self::ARRAY_ADDITIONAL_ITEMS,
                                "/{$index}",
                                "/additionalItems",
                                "Additional items (index " . count($items) . " or more) are not allowed"
                            );
                        } else if ($additionalitems !== true) {
                            $subresult = $this->subresult($subdata, $additionalitems);
                            $this->includesubresult($subresult, "/{$index}", "/additionalItems");
                        }
                    }
                }
            } else {
                foreach ($this->data as $index => &$subdata) {
                    if (!is_numeric($index)) {
                        throw new Exception("Arrays must only be numerically-indexed");
                    }
                    $subresult = $this->subresult($subdata, $items);
                    $this->includesubresult($subresult, "/{$index}", "/items");
                }
            }
        }
        if (isset($this->schema->minItems)) {
            if (count($this->data) < $this->schema->minItems) {
                $message = "Array is too short (must have at least {$this->schema->minItems} items)";
                $this->fail(self::ARRAY_LENGTH_SHORT, "", "/minItems", $message);
            }
        }
        if (isset($this->schema->maxItems)) {
            if (count($this->data) > $this->schema->maxItems) {
                $message = "Array is too long (must have at most {$this->schema->maxItems} items)";
                $this->fail(self::ARRAY_LENGTH_LONG, "", "/maxItems", $message);
            }
        }
        if (isset($this->schema->uniqueItems)) {
            foreach ($this->data as $indexa => $itema) {
                foreach ($this->data as $indexb => $itemb) {
                    if ($indexa < $indexb) {
                        if (self::recursiveequal($itema, $itemb)) {
                            $message = "Array items must be unique (items $indexa and $indexb)";
                            $this->fail(self::ARRAY_UNIQUE, "", "/uniqueItems", $message);
                            break 2;
                        }
                    }
                }
            }
        }
    }


    /**
     * Validates the data against the schema's string-related keywords (minLength, maxLength, pattern).
     *
     * @return void
     */
    private function checkstring() {
        if (!is_string($this->data)) {
            return;
        }
        if (isset($this->schema->minLength)) {
            if (mb_strlen($this->data) < $this->schema->minLength) {
                $message = "String must be at least {$this->schema->minLength} characters long";
                $this->fail(self::STRING_LENGTH_SHORT, "", "/minLength", $message);
            }
        }
        if (isset($this->schema->maxLength)) {
            if (mb_strlen($this->data) > $this->schema->maxLength) {
                $message = "String must be at most {$this->schema->maxLength} characters long";
                $this->fail(self::STRING_LENGTH_LONG, "", "/maxLength", $message);
            }
        }
        if (isset($this->schema->pattern)) {
            $pattern         = $this->schema->pattern;
            $patternflags    = isset($this->schema->patternFlags) ? $this->schema->patternFlags : '';
            $result          = preg_match("/" . str_replace("/", "\\/", $pattern) . "/" . $patternflags, $this->data);
            if ($result === 0) {
                $this->fail(self::STRING_PATTERN, "", "/pattern", "String does not match pattern: $pattern");
            }
        }
    }


    /**
     * Validates the data against the schema's numeric keywords (multipleOf, minimum, maximum).
     *
     * @return void
     */
    private function checknumber() {
        if (is_string($this->data) || !is_numeric($this->data)) {
            return;
        }
        if (isset($this->schema->multipleOf)) {
            if (fmod($this->data / $this->schema->multipleOf, 1) != 0) {
                $message = "Number must be a multiple of {$this->schema->multipleOf}";
                $this->fail(self::NUMBER_MULTIPLE_OF, "", "/multipleOf", $message);
            }
        }
        if (isset($this->schema->minimum)) {
            $minimum = $this->schema->minimum;
            if (isset($this->schema->exclusiveMinimum) && $this->schema->exclusiveMinimum) {
                if ($this->data <= $minimum) {
                    $this->fail(self::NUMBER_MINIMUM_EXCLUSIVE, "", "", "Number must be > $minimum");
                }
            } else {
                if ($this->data < $minimum) {
                    $this->fail(self::NUMBER_MINIMUM, "", "/minimum", "Number must be >= $minimum");
                }
            }
        }
        if (isset($this->schema->maximum)) {
            $maximum = $this->schema->maximum;
            if (isset($this->schema->exclusiveMaximum) && $this->schema->exclusiveMaximum) {
                if ($this->data >= $maximum) {
                    $this->fail(self::NUMBER_MAXIMUM_EXCLUSIVE, "", "", "Number must be < $maximum");
                }
            } else {
                if ($this->data > $maximum) {
                    $this->fail(self::NUMBER_MAXIMUM, "", "/maximum", "Number must be <= $maximum");
                }
            }
        }
    }


    /**
     * Validates the data against the schema's composite keywords (allOf, anyOf, oneOf, not).
     *
     * @return void
     */
    private function checkcomposite() {
        if (isset($this->schema->allOf)) {
            foreach ($this->schema->allOf as $index => $subschema) {
                $subresult = $this->subresult($this->data, $subschema, false);
                $this->includesubresult($subresult, "", "/allOf/" . (int) $index);
            }
        }
        if (isset($this->schema->anyOf)) {
            $failresults = [];
            foreach ($this->schema->anyOf as $index => $subschema) {
                $subresult = $this->subresult($this->data, $subschema, false);
                if ($subresult->valid) {
                    return;
                }
                $failresults[] = $subresult;
            }
            $this->fail(self::ANY_OF_MISSING, "", "/anyOf", "Value must satisfy at least one of the options", $failresults);
        }
        if (isset($this->schema->oneOf)) {
            $failresults     = [];
            $successindex    = null;
            foreach ($this->schema->oneOf as $index => $subschema) {
                $subresult = $this->subresult($this->data, $subschema, false);
                if ($subresult->valid) {
                    if ($successindex === null) {
                        $successindex = $index;
                    } else {
                        $message = "Value satisfies more than one of the options ($successindex and $index)";
                        $this->fail(self::ONE_OF_MULTIPLE, "", "/oneOf", $message);
                    }
                    continue;
                }
                $failresults[] = $subresult;
            }
            if ($successindex === null) {
                $this->fail(self::ONE_OF_MISSING, "", "/oneOf", "Value must satisfy one of the options", $failresults);
            }
        }
        if (isset($this->schema->not)) {
            $subresult = $this->subresult($this->data, $this->schema->not, false);
            if ($subresult->valid) {
                $this->fail(self::NOT_PASSED, "", "/not", "Value satisfies prohibited schema");
            }
        }
    }


    /**
     * Creates a default value for a missing required property, when coercion is enabled.
     *
     * @param string $key the property name.
     * @return bool true when a default value could be created.
     */
    private function createvalueforproperty($key) {
        $schema = null;
        if (isset($this->schema->properties->$key)) {
            $schema = $this->schema->properties->$key;
        } else if (isset($this->schema->patternProperties)) {
            foreach ($this->schema->patternProperties as $pattern => $subschema) {
                if (preg_match("/" . str_replace("/", "\\/", $pattern) . "/", $key)) {
                    $schema = $subschema;
                    break;
                }
            }
        }
        if (!$schema && isset($this->schema->additionalProperties)) {
            $schema = $this->schema->additionalProperties;
        }
        if ($schema) {
            if (isset($schema->default)) {
                $this->data->$key = unserialize(serialize($schema->default));
                return true;
            }
            if (isset($schema->type)) {
                $types = is_array($schema->type) ? $schema->type : [$schema->type];
                if (in_array("null", $types)) {
                    $this->data->$key = null;
                } else if (in_array("boolean", $types)) {
                    $this->data->$key = true;
                } else if (in_array("integer", $types) || in_array("number", $types)) {
                    $this->data->$key = 0;
                } else if (in_array("string", $types)) {
                    $this->data->$key = "";
                } else if (in_array("object", $types)) {
                    $this->data->$key = new \StdClass();
                } else if (in_array("array", $types)) {
                    $this->data->$key = [];
                } else {
                    return false;
                }
            }
            return true;
        }
        return false;
    }
}

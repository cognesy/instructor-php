<?php declare(strict_types=1);

namespace Cognesy\Utils\JsonSchema\Traits\JsonSchemaType;

use Cognesy\Utils\Arrays;
use Cognesy\Utils\JsonSchema\JsonSchemaType;

trait HandlesAccess
{
    public function isAny() : bool {
        return empty($this->types) || Arrays::valuesMatch(JsonSchemaType::JSON_ANY_TYPES, $this->types);
    }

    public function isArray() : bool {
        return count($this->types) === 1
            && in_array(JsonSchemaType::JSON_ARRAY, $this->types);
    }

    public function isObject() : bool {
        return count($this->types) === 1
            && in_array(JsonSchemaType::JSON_OBJECT, $this->types);
    }

    public function isString() : bool {
        return count($this->types) === 1
            && in_array(JsonSchemaType::JSON_STRING, $this->types);
    }

    public function isInteger() : bool {
        return count($this->types) === 1
            && in_array(JsonSchemaType::JSON_INTEGER, $this->types);
    }

    public function isBoolean() : bool {
        return count($this->types) === 1
            && in_array(JsonSchemaType::JSON_BOOLEAN, $this->types);
    }

    public function isNumber() : bool {
        return count($this->types) === 1
            && in_array(JsonSchemaType::JSON_NUMBER, $this->types);
    }

    public function isNull() : bool {
        return count($this->types) === 1
            && in_array(JsonSchemaType::JSON_NULL, $this->types);
    }

    public function isScalar() : bool {
        foreach($this->types as $type) {
            if (!in_array($type, JsonSchemaType::JSON_SCALAR_TYPES)) {
                return false;
            }
        }
        return true;
    }
}
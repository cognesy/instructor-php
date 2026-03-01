<?php declare(strict_types=1);

namespace Cognesy\Utils\JsonSchema\Traits\JsonSchemaType;

use Cognesy\Utils\Arrays;
use Cognesy\Utils\JsonSchema\JsonSchemaType;

trait HandlesAccess
{
    public function isAny() : bool {
        $types = $this->nonNullTypes();
        return $types === [] || Arrays::valuesMatch(JsonSchemaType::JSON_ANY_TYPES, $types);
    }

    public function isArray() : bool {
        $types = $this->nonNullTypes();
        return count($types) === 1
            && in_array(JsonSchemaType::JSON_ARRAY, $types, true);
    }

    public function isObject() : bool {
        $types = $this->nonNullTypes();
        return count($types) === 1
            && in_array(JsonSchemaType::JSON_OBJECT, $types, true);
    }

    public function isString() : bool {
        $types = $this->nonNullTypes();
        return count($types) === 1
            && in_array(JsonSchemaType::JSON_STRING, $types, true);
    }

    public function isInteger() : bool {
        $types = $this->nonNullTypes();
        return count($types) === 1
            && in_array(JsonSchemaType::JSON_INTEGER, $types, true);
    }

    public function isBoolean() : bool {
        $types = $this->nonNullTypes();
        return count($types) === 1
            && in_array(JsonSchemaType::JSON_BOOLEAN, $types, true);
    }

    public function isNumber() : bool {
        $types = $this->nonNullTypes();
        return count($types) === 1
            && in_array(JsonSchemaType::JSON_NUMBER, $types, true);
    }

    public function isNull() : bool {
        return count($this->types) === 1
            && in_array(JsonSchemaType::JSON_NULL, $this->types, true);
    }

    public function isScalar() : bool {
        $types = $this->nonNullTypes();
        if ($types === []) {
            return false;
        }

        foreach ($types as $type) {
            if (!in_array($type, JsonSchemaType::JSON_SCALAR_TYPES, true)) {
                return false;
            }
        }
        return true;
    }

    /** @return list<string> */
    private function nonNullTypes() : array {
        return array_values(array_filter(
            $this->types,
            static fn(string $type) : bool => $type !== JsonSchemaType::JSON_NULL,
        ));
    }
}

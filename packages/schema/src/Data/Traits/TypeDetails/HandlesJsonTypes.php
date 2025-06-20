<?php

namespace Cognesy\Schema\Data\Traits\TypeDetails;

use Cognesy\Utils\JsonSchema\JsonSchema;

trait HandlesJsonTypes
{
    public function jsonType() : string {
        return match ($this->type) {
            self::PHP_OBJECT => JsonSchema::JSON_OBJECT,
            self::PHP_ENUM => ($this->enumType === self::PHP_INT ? JsonSchema::JSON_INTEGER : JsonSchema::JSON_STRING),
            self::PHP_COLLECTION => JsonSchema::JSON_ARRAY,
            self::PHP_ARRAY => JsonSchema::JSON_ARRAY,
            self::PHP_INT => JsonSchema::JSON_INTEGER,
            self::PHP_FLOAT => JsonSchema::JSON_NUMBER,
            self::PHP_STRING => JsonSchema::JSON_STRING,
            self::PHP_BOOL => JsonSchema::JSON_BOOLEAN,
            default => throw new \Exception('Type not supported: '.$this->type),
        };
    }

    static public function toPhpType(string $jsonType) : string {
        return match ($jsonType) {
            JsonSchema::JSON_OBJECT => self::PHP_OBJECT,
            JsonSchema::JSON_ARRAY => self::PHP_ARRAY,
            JsonSchema::JSON_INTEGER => self::PHP_INT,
            JsonSchema::JSON_NUMBER => self::PHP_FLOAT,
            JsonSchema::JSON_STRING => self::PHP_STRING,
            JsonSchema::JSON_BOOLEAN => self::PHP_BOOL,
            default => throw new \Exception('Unknown type: ' . $jsonType),
        };
    }
}
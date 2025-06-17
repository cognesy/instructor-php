<?php

namespace Cognesy\Schema\Data\Traits\TypeDetails;

trait HandlesJsonTypes
{
    public function jsonType() : string {
        return match ($this->type) {
            self::PHP_OBJECT => self::JSON_OBJECT,
            self::PHP_ENUM => ($this->enumType === self::PHP_INT ? self::JSON_INTEGER : self::JSON_STRING),
            self::PHP_COLLECTION => self::JSON_ARRAY,
            self::PHP_ARRAY => self::JSON_ARRAY,
            self::PHP_INT => self::JSON_INTEGER,
            self::PHP_FLOAT => self::JSON_NUMBER,
            self::PHP_STRING => self::JSON_STRING,
            self::PHP_BOOL => self::JSON_BOOLEAN,
            default => throw new \Exception('Type not supported: '.$this->type),
        };
    }

    static public function toPhpType(string $jsonType) : string {
        return match ($jsonType) {
            self::JSON_OBJECT => self::PHP_OBJECT,
            self::JSON_ARRAY => self::PHP_ARRAY,
            self::JSON_INTEGER => self::PHP_INT,
            self::JSON_NUMBER => self::PHP_FLOAT,
            self::JSON_STRING => self::PHP_STRING,
            self::JSON_BOOLEAN => self::PHP_BOOL,
            default => throw new \Exception('Unknown type: '.$jsonType),
        };
    }
}
<?php

namespace Cognesy\Schema\Data\Traits\TypeDetails;

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\JsonSchemaType;

trait HandlesJsonTypes
{
    public function toJsonType() : JsonSchemaType {
        return match ($this->type) {
            self::PHP_OBJECT => JsonSchemaType::object(),
            self::PHP_ENUM => ($this->enumType === self::PHP_INT ? JsonSchemaType::integer() : JsonSchemaType::string()),
            self::PHP_COLLECTION => JsonSchemaType::array(),
            self::PHP_ARRAY => JsonSchemaType::array(),
            self::PHP_INT => JsonSchemaType::integer(),
            self::PHP_FLOAT => JsonSchemaType::number(),
            self::PHP_STRING => JsonSchemaType::string(),
            self::PHP_BOOL => JsonSchemaType::boolean(),
            default => throw new \Exception('Type not supported: '.$this->type),
        };
    }

    static public function jsonToPhpType(JsonSchemaType $jsonType) : string {
        return match (true) {
            $jsonType->isObject() => self::PHP_OBJECT,
            $jsonType->isArray() => self::PHP_ARRAY,
            $jsonType->isInteger() => self::PHP_INT,
            $jsonType->isNumber() => self::PHP_FLOAT,
            $jsonType->isString() => self::PHP_STRING,
            $jsonType->isBoolean() => self::PHP_BOOL,
            default => throw new \Exception('Unknown type: ' . $jsonType->toString()),
        };
    }

    static public function fromJson(JsonSchema $json) : TypeDetails {
        return match (true) {
            $json->isOption() => TypeDetails::option($json->enumValues()),
            $json->isObject() && !$json->hasObjectClass() => throw new \Exception('Object must have x-php-class field with the target class name'),
            $json->isObject() => TypeDetails::object($json->objectClass()),
            $json->isEnum() => TypeDetails::enum($json->objectClass(), self::jsonToPhpType($json->type()), $json->enumValues()),
            $json->isCollection() => TypeDetails::collection(match (true) {
                $json->itemSchema()?->isOption() => TypeDetails::option($json->itemSchema()?->enumValues()),
                $json->itemSchema()?->isEnum() => $json->itemSchema()?->objectClass(),
                $json->itemSchema()?->isObject() => $json->itemSchema()?->objectClass(),
                $json->itemSchema()?->isScalar() => self::jsonToPhpType($json->itemSchema()?->type()),
                $json->itemSchema()?->isAny() => TypeDetails::mixed(),
                default => throw new \Exception('Collection item type must be scalar, object or enum: ' . $json->itemSchema()?->type()->toString()),
            }),
            $json->isArray() => TypeDetails::array(),
            $json->isString() => TypeDetails::string(),
            $json->isBoolean() => TypeDetails::bool(),
            $json->isInteger() => TypeDetails::int(),
            $json->isNumber() => TypeDetails::float(),
            $json->isAny() => TypeDetails::mixed(),
            default => throw new \Exception('Unknown type: ' . $json->type()->toString()),
        };
    }
}
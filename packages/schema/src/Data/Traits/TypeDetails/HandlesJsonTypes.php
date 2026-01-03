<?php declare(strict_types=1);

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
            $json->isObject() && !$json->hasObjectClass() => TypeDetails::array(), // Return array when no class specified
            $json->isObject() => (function() use ($json) {
                /** @var class-string $objectClass */
                $objectClass = $json->objectClass() ?? throw new \Exception('Object class is required');
                return TypeDetails::object($objectClass);
            })(),
            $json->isEnum() => (function() use ($json) {
                /** @var class-string $enumClass */
                $enumClass = $json->objectClass() ?? throw new \Exception('Enum class is required');
                return TypeDetails::enum($enumClass, self::jsonToPhpType($json->type()), $json->enumValues());
            })(),
            $json->isCollection() => TypeDetails::collection(match (true) {
                $json->itemSchema()?->isOption() => self::PHP_STRING,
                $json->itemSchema()?->isEnum() => $json->itemSchema()->objectClass() ?? throw new \Exception('Enum class is required'),
                ($json->itemSchema()?->isObject() === true) && !$json->itemSchema()->hasObjectClass() => self::PHP_ARRAY, // Return array when no class
                $json->itemSchema()?->isObject() => $json->itemSchema()->objectClass() ?? throw new \Exception('Object class is required'),
                $json->itemSchema()?->isScalar() => self::jsonToPhpType($json->itemSchema()->type()),
                $json->itemSchema()?->isAny() => self::PHP_MIXED,
                default => throw new \Exception('Collection item type must be scalar, object or enum: ' . ($json->itemSchema()?->type()->toString() ?? 'unknown')),
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
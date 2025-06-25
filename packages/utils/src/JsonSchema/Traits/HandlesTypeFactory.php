<?php

namespace Cognesy\Utils\JsonSchema\Traits;

use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\JsonSchemaType;

trait HandlesTypeFactory
{
    public static function array(
        string      $name = '',
        ?JsonSchema $itemSchema = null,
        ?bool       $nullable = null,
        ?string     $description = null,
        ?string     $title = null,
        array       $meta = [],
    ) : self {
        return new self(
            type: JsonSchemaType::array(),
            name: $name,
            nullable: $nullable,
            itemSchema: $itemSchema,
            description: $description,
            title: $title,
            meta: $meta,
        );
    }

    public static function object(
        string  $name = '',
        array   $properties = [],
        ?bool   $nullable = null,
        ?array  $requiredProperties = null,
        ?string $description = null,
        ?string $title = null,
        bool    $additionalProperties = false,
        array   $meta = [],
    ) : self {
        return new self(
            type: JsonSchemaType::object(),
            name: $name,
            nullable: $nullable,
            properties: $properties,
            requiredProperties: $requiredProperties,
            additionalProperties: $additionalProperties,
            description: $description,
            title: $title,
            meta: $meta,
        );
    }

    public static function enum(
        string $name = '',
        array  $enumValues = [],
        ?bool  $nullable = null,
        string $description = '',
        string $title = '',
        array  $meta = [],
    ) : self {
        return new self(
            type: JsonSchemaType::string(),
            name: $name,
            nullable: $nullable,
            enumValues: $enumValues,
            description: $description,
            title: $title,
            meta: $meta,
        );
    }

    public static function string(
        string $name = '',
        ?bool $nullable = null,
        string $description = '',
        string $title = '',
        array $meta = [],
    ) : self {
        return new self(
            type: JsonSchemaType::string(),
            name: $name,
            nullable: $nullable,
            description: $description,
            title: $title,
            meta: $meta,
        );
    }

    public static function boolean(
        string $name = '',
        ?bool $nullable = null,
        string $description = '',
        string $title = '',
        array $meta = [],
    ) : self {
        return new self(
            type: JsonSchemaType::boolean(),
            name: $name,
            nullable: $nullable,
            description: $description,
            title: $title,
            meta: $meta,
        );
    }

    public static function number(
        string $name = '',
        ?bool $nullable = null,
        string $description = '',
        string $title = '',
        array $meta = [],
    ) : self {
        return new self(
            type: JsonSchemaType::number(),
            name: $name,
            nullable: $nullable,
            description: $description,
            title: $title,
            meta: $meta,
        );
    }

    public static function integer(
        string $name = '',
        ?bool $nullable = null,
        string $description = '',
        string $title = '',
        array $meta = [],
    ) : self {
        return new self(
            type: JsonSchemaType::integer(),
            name: $name,
            nullable: $nullable,
            description: $description,
            title: $title,
            meta: $meta,
        );
    }

    public static function collection(
        string $name = '',
        ?JsonSchema $itemSchema = null,
        ?bool $nullable = null,
        string $description = '',
        string $title = '',
        array $meta = [],
    ) : self {
        return new self(
            type: JsonSchemaType::array(),
            name: $name,
            nullable: $nullable,
            itemSchema: $itemSchema,
            description: $description,
            title: $title,
            meta: $meta,
        );
    }
}
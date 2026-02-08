<?php declare(strict_types=1);

namespace Cognesy\Utils\JsonSchema\Traits;

use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\JsonSchemaType;

trait HandlesTypeFactory
{
    public static function array(
        string      $name = '',
        ?JsonSchema $itemSchema = null,
        ?string     $description = null,
        ?string     $title = null,
        ?bool       $nullable = null,
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
        ?array  $requiredProperties = null,
        ?string $description = null,
        ?string $title = null,
        bool    $additionalProperties = false,
        ?bool   $nullable = null,
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
        string $description = '',
        string $title = '',
        ?bool  $nullable = null,
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
        string $description = '',
        string $title = '',
        ?bool $nullable = null,
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
        string $description = '',
        string $title = '',
        ?bool $nullable = null,
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
        string $description = '',
        string $title = '',
        ?bool $nullable = null,
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
        string $description = '',
        string $title = '',
        ?bool $nullable = null,
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
        string $description = '',
        string $title = '',
        ?bool $nullable = null,
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

    /**
     * Create a schema that accepts any type (no type constraint).
     * Useful for dynamic values like metadata storage.
     */
    public static function any(
        string $name = '',
        string $description = '',
        string $title = '',
        ?bool $nullable = null,
        array $meta = [],
    ) : self {
        return new self(
            type: JsonSchemaType::any(),
            name: $name,
            nullable: $nullable,
            description: $description,
            title: $title,
            meta: $meta,
        );
    }
}
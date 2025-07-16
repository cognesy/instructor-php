<?php declare(strict_types=1);

namespace Cognesy\Utils\JsonSchema;

use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;
use Exception;

/**
 * JSON Schema definition API - helps to specify JSON Schema
 * using static factory and fluent methods.
 *
 * EXAMPLE #1:
 *
 *    $schema = JsonSchema::object(
 *        name: 'User',
 *        description: 'User object',
 *        properties: [
 *            JsonSchema::string(name: 'id'),
 *            JsonSchema::string(name: 'name'),
 *        ],
 *        required: ['id', 'name'],
 *    );
 *
 * EXAMPLE #2:
 *
 *    $schema2 = JsonSchema::array('list')
 *        ->withItemSchema(JsonSchema::string())
 *        ->withRequired(['id', 'name']);
 *
 */
class JsonSchema implements CanProvideJsonSchema
{
    use Traits\HandlesAccess;
    use Traits\HandlesMutation;
    use Traits\HandlesTypeFactory;
    use Traits\HandlesTransformation;

    protected JsonSchemaType $type;
    protected string $name;
    protected ?bool $nullable = null;
    /** @var JsonSchema[]|null */
    protected ?array $properties = null;
    /** @var array<string>|null */
    protected ?array $requiredProperties = null;
    protected ?JsonSchema $itemSchema = null;
    /** @var array<string>|null */
    protected ?array $enumValues = null;
    protected ?bool $additionalProperties = null;
    protected ?string $description = null;
    protected ?string $title = null;
    /** @var array<string, mixed> */
    protected array $meta = [];

    private function __construct(
        JsonSchemaType $type,
        string      $name = '',
        ?bool       $nullable = null,
        ?array      $properties = null,
        ?array      $requiredProperties = null,
        ?JsonSchema $itemSchema = null,
        ?array      $enumValues = null,
        ?bool       $additionalProperties = null,
        ?string     $description = null,
        ?string     $title = null,
        array       $meta = [],
        // public array $oneOf = [],
        // public array $anyOf = [],
        // public array $allOf = [],
        // public array $not = [],
        // public array $dependencies = [],
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->nullable = $nullable;
        $this->requiredProperties = $requiredProperties;
        $this->itemSchema = $itemSchema;
        $this->enumValues = $enumValues;
        $this->additionalProperties = $additionalProperties;
        $this->description = $description;
        $this->title = $title;
        $this->meta = $meta;

        if ($this->enumValues !== null) {
            // validate enum values are strings
            foreach ($this->enumValues as $value) {
                if (!is_string($value)) {
                    throw new Exception('Invalid JSON type: invalid in: ' . $this->name . ' - enum values must be strings');
                }
            }
        }

        $this->properties = self::toKeyedProperties($properties);
    }

    // MAIN ////////////////////////////////////////////////////////////////////

    public static function fromArray(array $data, ?string $name = null, ?bool $required = null) : JsonSchema {
        if (empty($data)) {
            return new self(
                type: JsonSchemaType::any(),
                name: $name ?? '',
                nullable: true,
            );
        }

        $type = JsonSchemaType::fromJsonData($data, is_null($required) ? null : !$required);

        $properties = [];
        if (isset($data['properties'])) {
            foreach ($data['properties'] as $propertyName => $propertyData) {
                $isRequired = in_array($propertyName, $data['required'] ?? []);
                $properties[$propertyName] = JsonSchema::fromArray($propertyData, $propertyName, $isRequired);
            }
            $properties = array_filter($properties);
        }

        $itemSchema = match(true) {
            isset($data['items']) => JsonSchema::fromArray($data['items']),
            default => null,
        };

        return new self(
            type: $type,
            name: $name ?? $data['name'] ?? $data['x-name'] ?? '',
            nullable: $data['nullable'] ?? null,
            properties: $properties,
            requiredProperties: $data['required'] ?? null,
            itemSchema: $itemSchema,
            enumValues: $data['enum'] ?? null,
            additionalProperties: $data['additionalProperties'] ?? null,
            description: $data['description'] ?? '',
            title: $data['title'] ?? '',
            meta: self::extractMetaFields(
                data: $data,
                excludedFields: ['type', 'nullable', 'properties', 'required', 'items', 'enum', 'additionalProperties', 'title', 'description']
            ),
        );
    }

    // INTERNAL ///////////////////////////////////////////////////////////////

    private static function extractMetaFields(array $data, array $excludedFields) : array {
        $meta = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $excludedFields)) {
                continue;
            }
            // if key starts with x- add it to meta
            if (str_starts_with($key, 'x-')) {
                $actualKey = substr($key, 2);
                $meta[$actualKey] = $value;
            }
        }
        return $meta;
    }

    private static function toKeyedProperties(?array $properties) : array {
        if (empty($properties)) {
            return [];
        }

        // supported property values:
        //  a) string-key => array
        //  b) string-key => object
        //  c) int => array
        //  d) int => object
        // and turn them into keyed array of JsonSchema objects

        $keyedProperties = [];
        foreach ($properties as $key => $property) {
            $index = match(true) {
                is_int($key) && is_array($property) => $property['name'] ?? $property['x-name'] ?? null,
                is_int($key) && is_object($property) => $property->name ?? $property->meta['name'] ?? $property->meta['x-name'] ?? null,
                is_string($key) => $key,
                default => null,
            };

            if ($index === null) {
                throw new Exception('Missing property name: ' . print_r($property, true));
            }

            $keyedProperties[$index] = match(true) {
                $property instanceof JsonSchema => $property->withName($index),
                is_array($property) => JsonSchema::fromArray($property)?->withName($index),
                default => throw new Exception('Invalid property: ' . print_r($property, true)),
            };
        }
        return $keyedProperties;
    }
}

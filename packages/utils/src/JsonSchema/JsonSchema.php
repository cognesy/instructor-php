<?php

namespace Cognesy\Utils\JsonSchema;

use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

class JsonSchema implements CanProvideJsonSchema
{
    use \Cognesy\Utils\JsonSchema\Traits\HandlesAccess;
    use \Cognesy\Utils\JsonSchema\Traits\HandlesMutation;
    use \Cognesy\Utils\JsonSchema\Traits\HandlesTypeFactory;
    use \Cognesy\Utils\JsonSchema\Traits\HandlesTransformation;

    public string $type;
    public string $name;
    public ?bool $nullable = null;
    /** @var JsonSchema[]|null */
    public ?array $properties = null;
    /** @var array<string>|null */
    public ?array $requiredProperties = null;
    public ?JsonSchema $itemSchema = null;
    /** @var array<string>|null */
    public ?array $enumValues = null;
    public ?bool $additionalProperties = null;
    public ?string $description = null;
    public ?string $title = null;
    /** @var array<string, mixed> */
    public array $meta = [];

    public function __construct(
        string      $type,
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

        $this->properties = $this->toKeyedProperties($properties);

        $validator = new JsonSchemaValidator();
        $validator->validate($this);
    }

    // MAIN ////////////////////////////////////////////////////////////////////

    public static function fromArray(array $data, ?string $name = null) : ?JsonSchema {
        if (empty($data)) {
            return null;
        }

        if (!isset($data['type'])) {
            throw new \Exception('Invalid schema: missing "type"');
        }

        $properties = [];
        if (isset($data['properties'])) {
            foreach ($data['properties'] as $propertyName => $propertyData) {
                $properties[$propertyName] = JsonSchema::fromArray($propertyData, $propertyName);
            }
            $properties = array_filter($properties);
        }

        $items = match(true) {
            isset($data['items']) => JsonSchema::fromArray($data['items']),
            default => null,
        };

        return new self(
            type: $data['type'],
            name: $name ?? $data['name'] ?? $data['x-name'] ?? '',
            nullable: $data['nullable'] ?? null,
            properties: $properties,
            requiredProperties: $data['required'] ?? null,
            itemSchema: $items,
            enumValues: $data['enum'] ?? null,
            additionalProperties: $data['additionalProperties'] ?? null,
            description: $data['description'] ?? '',
            title: $data['title'] ?? '',
            meta: self::extractMetaFields($data, [
                'type', 'nullable', 'properties', 'required', 'items', 'enum', 'additionalProperties', 'title', 'description',
            ]),
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
            if (strpos($key, 'x-') === 0) {
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

        // supported properties values:
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
                throw new \Exception('Missing property name: ' . print_r($property, true));
            }

            $keyedProperties[$index] = match(true) {
                $property instanceof JsonSchema => $property->withName($index),
                is_array($property) => JsonSchema::fromArray($property)->withName($index),
                default => throw new \Exception('Invalid property: ' . print_r($property, true)),
            };
        }
        return $keyedProperties;
    }
}

//EXAMPLES:
//$schema = JsonSchema::object(
//    name: 'User',
//    description: 'User object',
//    properties: [
//        JsonSchema::string(name: 'id'),
//        JsonSchema::string(name: 'name'),
//    ],
//    required: ['id', 'name'],
//);
//
//$schema2 = JsonSchema::array('list')
//    ->withItemSchema(JsonSchema::string())
//    ->withRequired(['id', 'name']);

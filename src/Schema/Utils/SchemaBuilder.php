<?php
namespace Cognesy\Instructor\Schema\Utils;

use Cognesy\Instructor\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\Data\Schema\EnumSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\ScalarSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Data\TypeDetails;

/**
 * Builds Schema object from JSON Schema array
 * Used by ResponseModel to derive schema from raw JSON Schema format,
 * when full processing customization is needed.
 * Requires $comments field to contain the target class name for each object and enum type.
 */
class SchemaBuilder
{
    /**
     * Create Schema object from given JSON Schema array
     */
    public function fromArray(
        array $jsonSchema,
        string $customName = 'extracted_object',
        string $customDescription = 'Data extracted from chat content'
    ) : ObjectSchema {
        $class = $jsonSchema['$comment'] ?? null;
        if (!$class) {
            throw new \Exception('JSON Schema must have $comment field with the target class name');
        }
        $type = $jsonSchema['type'] ?? null;
        if ($type !== 'object') {
            throw new \Exception('JSON Schema must have type: object');
        }
        return new ObjectSchema(
            type: new TypeDetails(
                type: 'object',
                class: $class,
                nestedType: null,
                enumType: null,
                enumValues: null,
            ),
            name: $customName ?? ($jsonSchema['title'] ?? 'extract_object'),
            description: $customDescription ?? ($jsonSchema['description'] ?? 'Extract parameters from content'),
            properties: $this->makeProperties($jsonSchema['properties'] ?? []),
            required: $jsonSchema['required'] ?? [],
        );
    }

    /**
     * Create property schemas array (for object)
     */
    private function makeProperties(array $properties) : array {
        if (empty($properties)) {
            throw new \Exception('Object must have at least one property');
        }
        foreach ($properties as $property => $propertySchema) {
            $properties[$property] = $this->makePropertySchema($property, $propertySchema);
        }
        return $properties;
    }

    /**
     * Create any property schema
     */
    private function makePropertySchema(string $name, array $jsonSchema) : Schema {
        return match ($jsonSchema['type']) {
            'object' => $this->makeObjectProperty($name, $jsonSchema),
            'array' => $this->makeArrayProperty($name, $jsonSchema),
            'string', 'boolean', 'number', 'integer' => $this->makeEnumOrScalarProperty($name, $jsonSchema),
            default => throw new \Exception('Unknown type: '.$jsonSchema['type']),
        };
    }

    /**
     * Create enum or scalar property schema
     */
    private function makeEnumOrScalarProperty(string $name, array $jsonSchema) : Schema {
        if (isset($jsonSchema['enum'])) {
            return $this->makeEnumProperty($name, $jsonSchema);
        }
        return match ($jsonSchema['type']) {
            'string', 'boolean', 'integer', 'number' => $this->makeScalarProperty($name, $jsonSchema),
            default => throw new \Exception('Unknown type: '.$jsonSchema['type']),
        };
    }

    /**
     * Create scalar property schema
     */
    private function makeScalarProperty(string $name, array $jsonSchema) : ScalarSchema {
        return new ScalarSchema(name: $name, description: $jsonSchema['description']??'', type: new TypeDetails(
                type: TypeDetails::fromJsonType($jsonSchema['type']),
                class: null,
                nestedType: null,
                enumType: null,
                enumValues: null,
            ),
        );
    }

    /**
     * Create enum property schema
     */
    private function makeEnumProperty(string $name, array $jsonSchema) : EnumSchema {
        if (!in_array($jsonSchema['type'], ['string', 'integer'])) {
            throw new \Exception('Enum type must be either string or int');
        }
        if (!($class = $jsonSchema['$comment']??null)) {
            throw new \Exception('Enum must have $comment field with the target class name');
        }
        return new EnumSchema(name: $name, description: $jsonSchema['description']??'', type: new TypeDetails(
                type: 'enum',
                class: $class,
                nestedType: null,
                enumType: TypeDetails::fromJsonType($jsonSchema['type']),
                enumValues: $jsonSchema['enum'],
            ),
        );
    }

    /**
     * Create array property schema
     */
    private function makeArrayProperty(string $name, array $jsonSchema) : ArraySchema {
        if (!isset($jsonSchema['items'])) {
            throw new \Exception('Array must have items field defining the nested type');
        }
        return new ArraySchema(name: $name, description: $jsonSchema['description']??'',
            type: new TypeDetails(
                type: 'array',
                class: null,
                nestedType: $this->makeNestedType($jsonSchema['items']),
                enumType: null,
                enumValues: null,
            ),
            nestedItemSchema: $this->makePropertySchema('', $jsonSchema['items']??[]),
        );
    }

    /**
     * Create object property schema
     */
    private function makeObjectProperty(string $name, array $jsonSchema) : ObjectSchema {
        if (!($class = $jsonSchema['$comment']??null)) {
            throw new \Exception('Object must have $comment field with the target class name');
        }
        return new ObjectSchema(name: $name, description: $jsonSchema['description']??'',
            type: new TypeDetails(
                type: 'object',
                class: $class,
                nestedType: null,
                enumType: null,
                enumValues: null,
            ),
            properties: $this->makeProperties($jsonSchema['properties']??[]),
            required: $jsonSchema['required'] ?? [],
        );
    }

    /**
     * Create nested type (for array property)
     */
    private function makeNestedType(array $jsonSchema) : TypeDetails {
        if ($jsonSchema['type'] === 'array') {
            throw new \Exception('Nested type cannot be array');
        }
        if ($jsonSchema['type'] === 'object') {
            if (!($class = $jsonSchema['$comment']??null)) {
                throw new \Exception('Nested type must have $comment field with the target class name');
            }
            return new TypeDetails(type: 'object', class: $class, nestedType: null, enumType: null, enumValues: null);
        }

        if ($jsonSchema['enum'] ?? false) {
            if (!in_array($jsonSchema['type'], ['string', 'integer'])) {
                throw new \Exception('Nested enum type must be either string or int');
            }
            if (!($class = $jsonSchema['$comment']??null)) {
                throw new \Exception('Nested enum type cannot have $comment field');
            }
            return new TypeDetails(type: 'enum', class: $class, nestedType: null, enumType: TypeDetails::fromJsonType($jsonSchema['type']), enumValues: $jsonSchema['enum']);
        }

        if (!in_array($jsonSchema['type'], ['string', 'integer', 'number', 'boolean'])) {
            throw new \Exception('Unknown type: '.$jsonSchema['type']);
        }
        return new TypeDetails(type: $jsonSchema['type'], class: null, nestedType: null, enumType: null, enumValues: null);
    }
}
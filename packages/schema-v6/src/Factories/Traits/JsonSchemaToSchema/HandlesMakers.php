<?php

namespace Cognesy\Schema\Factories\Traits\JsonSchemaToSchema;

use Cognesy\Schema\Data\Schema\ArraySchema;
use Cognesy\Schema\Data\Schema\CollectionSchema;
use Cognesy\Schema\Data\Schema\EnumSchema;
use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\OptionSchema;
use Cognesy\Schema\Data\Schema\ScalarSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Factories\TypeDetailsFactory;

trait HandlesMakers
{
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
            TypeDetails::JSON_OBJECT => $this->makeObjectProperty($name, $jsonSchema),
            TypeDetails::JSON_ARRAY => match(true) {
                $this->isCollection($jsonSchema) => $this->makeCollectionProperty($name, $jsonSchema),
                default => $this->makeArrayProperty($name, $jsonSchema),
            },
            TypeDetails::JSON_STRING,
            TypeDetails::JSON_BOOLEAN,
            TypeDetails::JSON_NUMBER,
            TypeDetails::JSON_INTEGER => $this->makeEnumOrScalarProperty($name, $jsonSchema),
            default => throw new \Exception('Unknown type: '.$jsonSchema['type']),
        };
    }

    /**
     * Create enum or scalar property schema
     */
    private function makeEnumOrScalarProperty(string $name, array $jsonSchema) : Schema {
        if (isset($jsonSchema['enum']) && !empty($jsonSchema['x-php-class'])) {
            return $this->makeEnumProperty($name, $jsonSchema);
        }
        if (isset($jsonSchema['enum'])) {
            return $this->makeOptionProperty($name, $jsonSchema);
        }
        return match (true) {
            in_array($jsonSchema['type'], TypeDetails::JSON_SCALAR_TYPES) => $this->makeScalarProperty($name, $jsonSchema),
            default => throw new \Exception('Unknown type: '.$jsonSchema['type']),
        };
    }

    /**
     * Create scalar property schema
     */
    private function makeScalarProperty(string $name, array $jsonSchema) : ScalarSchema {
        $factory = new TypeDetailsFactory();
        $type = $factory->scalarType(TypeDetails::toPhpType($jsonSchema['type']));
        return new ScalarSchema(
            type: $type,
            name: $name,
            description: $jsonSchema['description'] ?? '',
        );
    }

    /**
     * Create enum property schema
     */
    private function makeEnumProperty(string $name, array $jsonSchema) : EnumSchema {
        if (!in_array($jsonSchema['type'], [TypeDetails::JSON_STRING, TypeDetails::JSON_INTEGER])) {
            throw new \Exception('Enum type must be either string or int');
        }
        if (!($class = $jsonSchema['x-php-class'] ?? null)) {
            throw new \Exception('Enum must have x-php-class field with the target class name');
        }
        $factory = new TypeDetailsFactory();
        $type = $factory->enumType($class, TypeDetails::toPhpType($jsonSchema['type']), $jsonSchema['enum']);
        return new EnumSchema(type: $type, name: $name, description: $jsonSchema['description'] ?? '');
    }

    /**
     * Create option property schema
     */
    private function makeOptionProperty(string $name, array $jsonSchema) : OptionSchema {
        if (!$jsonSchema['type'] === TypeDetails::JSON_STRING) {
            throw new \Exception('Option type must be string');
        }
        if (!isset($jsonSchema['enum'])) {
            throw new \Exception('Option must have enum field');
        }
        $factory = new TypeDetailsFactory();
        return new OptionSchema(
            type: $factory->optionType($jsonSchema['enum']),
            name: $name,
            description: $jsonSchema['description'] ?? '',
        );
    }

    /**
     * Create array property schema
     */
    private function makeCollectionProperty(string $name, array $jsonSchema) : CollectionSchema {
        if (!isset($jsonSchema['items'])) {
            throw new \Exception('Collection must have items field defining the nested type');
        }
        if (!isset($jsonSchema['items']['x-php-class'])) {
            throw new \Exception('Collection must have items x-php-class field defining target class of the nested type');
        }
        $factory = new TypeDetailsFactory();
        return new CollectionSchema(
            type: $factory->collectionType($this->makeNestedType($jsonSchema['items'])),
            name: $name,
            description: $jsonSchema['description'] ?? '',
            nestedItemSchema: $this->makePropertySchema('', $jsonSchema['items'] ?? []),
        );
    }

    /**
     * Create array property schema
     */
    private function makeArrayProperty(string $name, array $jsonSchema) : ArraySchema {
        if (!isset($jsonSchema['items'])) {
            throw new \Exception('Array must have items field');
        }
        if ($jsonSchema['items']['type'] === 'array') {
            throw new \Exception('Nested type cannot be array');
        }
        if (!isset($jsonSchema['items']['anyOf'])) {
            if (!in_array($jsonSchema['items']['type'], ['object','string','integer','number','boolean'])) {
                throw new \Exception('Nested array type must be either object, string, integer, number or boolean');
            }
        }
        $factory = new TypeDetailsFactory();
        return new ArraySchema(
            type: $factory->arrayType(),
            name: $name,
            description: $jsonSchema['description'] ?? '',
        );
    }

    /**
     * Create object property schema
     */
    private function makeObjectProperty(string $name, array $jsonSchema) : ObjectSchema {
        if (!($class = $jsonSchema['x-php-class'] ?? null)) {
            throw new \Exception('Object must have x-php-class field with the target class name');
        }
        $factory = new TypeDetailsFactory();
        return new ObjectSchema(
            type: $factory->objectType($class),
            name: $name,
            description: $jsonSchema['description'] ?? '',
            properties: $this->makeProperties($jsonSchema['properties'] ?? []),
            required: $jsonSchema['required'] ?? [],
        );
    }

    /**
     * Create nested type (for array property)
     */
    private function makeNestedType(array $jsonSchema) : TypeDetails {
        if ($jsonSchema['type'] === TypeDetails::JSON_ARRAY) {
            throw new \Exception('Nested type cannot be array');
        }

        $factory = new TypeDetailsFactory();

        if ($jsonSchema['type'] === TypeDetails::JSON_OBJECT) {
            if (!($class = $jsonSchema['x-php-class'] ?? null)) {
                throw new \Exception('Nested type must have x-php-class field with the target class name');
            }
            return $factory->objectType($class);
        }

        if ($jsonSchema['enum'] ?? false) {
            if (!in_array($jsonSchema['type'], [TypeDetails::JSON_STRING, TypeDetails::JSON_INTEGER])) {
                throw new \Exception('Nested enum type must be either string or int');
            }
            if (!($class = $jsonSchema['x-php-class'] ?? null)) {
                throw new \Exception('Nested enum type needs x-php-class field');
            }
            return $factory->enumType($class, TypeDetails::toPhpType($jsonSchema['type']), $jsonSchema['enum']);
        }

        if (!in_array($jsonSchema['type'], TypeDetails::JSON_SCALAR_TYPES)) {
            throw new \Exception('Unknown type: '.$jsonSchema['type']);
        }
        return $factory->scalarType(TypeDetails::toPhpType($jsonSchema['type']));
    }

    /**
     * Check if schema property is a collection
     * TODO: document what is a collection for Instructor - e.g. is string[] a collection?
     */
    private function isCollection(array $jsonSchema) : bool {
        return $jsonSchema['type'] === 'array'
            && isset($jsonSchema['items'])
            && isset($jsonSchema['items']['type'])
            && in_array($jsonSchema['items']['type'], ['object','string','integer']) // ENUM(string/int) or OBJECT(object)
            && isset($jsonSchema['items']['x-php-class']);
    }
}

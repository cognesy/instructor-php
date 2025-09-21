<?php declare(strict_types=1);

namespace Cognesy\Schema\Factories;

use Cognesy\Schema\Data\Schema\ArraySchema;
use Cognesy\Schema\Data\Schema\CollectionSchema;
use Cognesy\Schema\Data\Schema\EnumSchema;
use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\OptionSchema;
use Cognesy\Schema\Data\Schema\ScalarSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Exception;

/**
 * Builds Schema object from JSON Schema array
 *
 * Used by ResponseModel to derive schema from raw JSON Schema format,
 * when full processing customization is needed.
 *
 * Requires x-php-class field to contain the target class name for each
 * object and enum type, otherwise it will use the default class.
 */
class JsonSchemaToSchema
{
    private $defaultToolName;
    private $defaultToolDescription;
    private $defaultOutputClass;

    public function __construct(
        string $defaultToolName = 'extract_object',
        string $defaultToolDescription = 'Extract data from chat content',
        string $defaultOutputClass = '',
    ) {
        $this->defaultToolName = $defaultToolName;
        $this->defaultToolDescription = $defaultToolDescription;
        $this->defaultOutputClass = $defaultOutputClass;
    }

    /**
     * Create Schema object from given JSON Schema array
     */
    public function fromJsonSchema(
        array $jsonSchema,
        string $customName = '',
        string $customDescription = '',
    ) : ObjectSchema {
        $json = JsonSchema::fromArray($jsonSchema);
        if (!$json->isObject()) {
            throw new Exception('Root JSON Schema must be an object');
        }
        $class = $json->objectClass() ?? $this->defaultOutputClass;
        return new ObjectSchema(
            type: TypeDetails::object($class),
            name: $customName ?? ($json->title() ?? $this->defaultToolName),
            description: $customDescription ?? ($json->description() ?? $this->defaultToolDescription),
            properties: $this->makeProperties($json->properties()),
            required: $json->requiredProperties(),
        );
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    /**
     * Create property schemas array (for object)
     * @param JsonSchema[] $properties
     * @returns Schema[]
     */
    private function makeProperties(array $properties) : array {
        if ($properties === []) {
            return [];
        }
        /** @var Schema[] $result */
        $result = [];
        foreach ($properties as $propertyName => $propertySchema) {
            $result[$propertyName] = $this->makePropertySchema($propertyName, $propertySchema);
        }
        return $result;
    }

    /**
     * Create any property schema
     */
    private function makePropertySchema(string $name, JsonSchema $json) : Schema {
        return match (true) {
            $json->isEnum() => $this->makeEnumOrOptionProperty($name, $json),
            $json->isObject() => $this->makeObjectProperty($name, $json),
            $json->isCollection() => $this->makeCollectionProperty($name, $json),
            $json->isArray() => $this->makeArrayProperty($name, $json),
            $json->isScalar() => $this->makeScalarProperty($name, $json),
            default => throw new \Exception('Unknown type: ' . $json->toString()),
        };
    }

    /**
     * Create enum or scalar property schema
     */
    private function makeEnumOrOptionProperty(string $name, JsonSchema $json) : Schema {
        if ($json->hasObjectClass()) {
            return $this->makeEnumProperty($name, $json);
        }

        if ($json->hasEnumValues()) {
            return $this->makeOptionProperty($name, $json);
        }

        return match (true) {
            $json->isScalar() => $this->makeScalarProperty($name, $json),
            default => throw new \Exception('Unknown type: ' . $json->toString()),
        };
    }

    /**
     * Create enum property schema
     */
    private function makeEnumProperty(string $name, JsonSchema $json) : EnumSchema {
//        $backingType = $json->type();
//        if (!$backingType->isString() && !$backingType->isInteger()) {
//            throw new \Exception('Enum type must be either string or int');
//        }
//        $class = $json->objectClass();
//        $type = TypeDetails::enum(
//            class:       $class,
//            backingType: TypeDetails::jsonToPhpType($backingType),
//            values:      $json->enumValues()
//        );
        return new EnumSchema(
            type:        TypeDetails::fromJson($json),
            name:        $name,
            description: $json->description()
        );
    }

    /**
     * Create option property schema
     */
    private function makeOptionProperty(string $name, JsonSchema $json) : OptionSchema {
//        $backingType = $json->type();
//        if (!$backingType->isString() && !$backingType->isInteger()) {
//            throw new \Exception('Enum type must be either string or int');
//        }
//        if (!$json->hasEnumValues()) {
//            throw new \Exception('Option must have enum field values defined');
//        }
//        return new OptionSchema(
//            type:        TypeDetails::option($json->enumValues()),
//            name:        $name,
//            description: $json->description(),
//        );
        return new OptionSchema(
            type:        TypeDetails::fromJson($json),
            name:        $name,
            description: $json->description(),
        );
    }

    /**
     * Create scalar property schema
     */
    private function makeScalarProperty(string $name, JsonSchema $json) : ScalarSchema {
//        $phpType = TypeDetails::jsonToPhpType($json->type());
//        $type = TypeDetails::scalar($phpType);
//        return new ScalarSchema(
//            type: $type,
//            name: $name,
//            description: $json->description(),
//        );
        return new ScalarSchema(
            type: TypeDetails::fromJson($json),
            name: $name,
            description: $json->description(),
        );
    }

    /**
     * Create array property schema
     */
    private function makeCollectionProperty(string $name, JsonSchema $json) : CollectionSchema {
//        if (!$json->hasItemSchema()) {
//            throw new \Exception('Collection must have items field defining the nested type');
//        }
//        if (!$json->hasItemType()) {
//            throw new \Exception('Collection must have item types specified');
//        }
//        return new CollectionSchema(
//            type:             TypeDetails::collection($this->makeNestedType($json->itemSchema())),
//            name:             $name,
//            description:      $json->description(),
//            nestedItemSchema: $this->makePropertySchema('', $json->itemSchema()),
//        );
        return new CollectionSchema(
            type:             TypeDetails::fromJson($json),
            name:             $name,
            description:      $json->description(),
            nestedItemSchema: $this->makePropertySchema('', $json->itemSchema()),
        );
    }

    /**
     * Create array property schema
     */
    private function makeArrayProperty(string $name, JsonSchema $json) : ArraySchema {
//        return new ArraySchema(
//            type:        TypeDetails::array(),
//            name:        $name,
//            description: $json->description(),
//        );
        return new ArraySchema(
            type:        TypeDetails::fromJson($json),
            name:        $name,
            description: $json->description(),
        );
    }

    /**
     * Create object property schema
     */
    private function makeObjectProperty(string $name, JsonSchema $json) : ObjectSchema {
        if (!$json->hasObjectClass()) {
            throw new \Exception('Object must have class specified via x-php-class field');
        }
//        return new ObjectSchema(
//            type:        TypeDetails::object($json->objectClass()),
//            name:        $name,
//            description: $json->description(),
//            properties:  $this->makeProperties($json->properties()),
//            required:    $json->requiredProperties(),
//        );
        return new ObjectSchema(
            type:        TypeDetails::fromJson($json),
            name:        $name,
            description: $json->description(),
            properties:  $this->makeProperties($json->properties()),
            required:    $json->requiredProperties(),
        );
    }

//    /**
//     * Create nested type (for array property)
//     */
//    private function makeNestedType(JsonSchema $json) : TypeDetails {
//        if ($json->isArray()) {
//            throw new \Exception('Nested type cannot be array');
//        }
//
//        if ($json->isEnum()) {
//            if (!$json->isString() && !$json->isInteger()) {
//                throw new \Exception('Nested enum type must be either string or int');
//            }
//            if (!($json->hasEnumValues() || $json->hasObjectClass())) {
//                throw new \Exception('Nested enum must have either enum values or x-php-class field defined');
//            }
//            return TypeDetails::enum(
//                class: $json->objectClass(),
//                backingType: TypeDetails::jsonToPhpType($json->type()),
//                values: $json->enumValues()
//            );
//        }
//
//        if ($json->isObject()) {
//            if (!$json->hasObjectClass()) {
//                throw new \Exception('Nested type must have x-php-class field with the target class name');
//            }
//            return TypeDetails::object($json->objectClass());
//        }
//
//        if ($json->isScalar()) {
//            return TypeDetails::scalar(type: TypeDetails::jsonToPhpType($json->type()));
//        }
//
//        throw new \Exception('Unknown nested type: ' . $json->toString());
//    }
}

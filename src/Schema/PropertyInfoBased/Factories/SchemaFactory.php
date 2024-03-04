<?php

namespace Cognesy\Instructor\Schema\PropertyInfoBased\Factories;

use Cognesy\Instructor\PropertyMap;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\EnumSchema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\ObjectRefSchema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\ScalarSchema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\Schema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\TypeDetails;
use Cognesy\Instructor\Schema\PropertyInfoBased\Utils\ClassInfo;
use Cognesy\Instructor\SchemaMap;

/**
 * Factory for creating schema objects from class names
 *
 * NOTE: Currently, OpenAI API does not work well with object references,
 * so we return the full object schema with all properties inlined.
 */
class SchemaFactory
{
    /** @var bool allows to render schema with object properties inlined or referenced */
    protected $useObjectReferences = false;
    protected SchemaMap $schemaMap;
    protected PropertyMap $propertyMap;

    public function __construct() {
        $this->schemaMap = new SchemaMap;
        $this->propertyMap = new PropertyMap;
    }

    /**
     * Extracts the schema from a class and constructs a function call
     *
     * @param string $anyType - class name, enum name or type name
     */
    public function schema(string $anyType) : Schema
    {
        if (!$this->schemaMap->has($anyType)) {
            $this->schemaMap->register($anyType, $this->makeSchema((new TypeDetailsFactory)->fromTypeName($anyType)));
        }
        return $this->schemaMap->get($anyType);
    }

    public function property(string $class, string $property) : Schema
    {
        if (!$this->propertyMap->has($class, $property)) {
            $this->propertyMap->register($class, $property, $this->getPropertySchema($class, $property));
        }
        return $this->propertyMap->get($class, $property);
    }

    /**
     * Gets all the property schemas of a class
     *
     * @param string $class
     * @return Schema[]
     */
    protected function getPropertySchemas(string $class) : array {
        $properties = (new ClassInfo)->getProperties($class);
        $propertySchemas = [];
        foreach ($properties as $property) {
            $propertySchemas[$property] = $this->property($class, $property);
        }
        return $propertySchemas;
    }

    /**
     * Gets the schema of a property
     *
     * @param string $class
     * @param string $property
     * @return Schema
     */
    protected function getPropertySchema(string $class, string $property) : Schema {
        $propertyInfoType = (new ClassInfo)->getType($class, $property);
        $type = (new TypeDetailsFactory)->fromPropertyInfo($propertyInfoType);
        $description = $this->getPropertyDescription($type, $class, $property);
        return $this->makePropertySchema($type, $property, $description);
    }

    protected function getPropertyDescription(TypeDetails $type, string $class, string $property) : string{
        if (in_array($type->type, ['object', 'enum'])) {
            $classDescription = (new ClassInfo)->getClassDescription($type->class);
        } else {
            $classDescription = '';
        }
        return implode("\n", array_filter([
            (new ClassInfo)->getPropertyDescription($class, $property),
            $classDescription,
        ]));
    }

    /**
     * Makes schema for top level item (depending on the type)
     *
     * @param TypeDetails $type
     * @param string $name
     * @param string $description
     * @return Schema
     */
    protected function makeSchema(TypeDetails $type) : Schema
    {
        return match ($type->type) {
            'object' => new ObjectSchema(
                $type,
                $type->class,
                (new ClassInfo)->getClassDescription($type->class),
                $this->getPropertySchemas($type->class),
                (new ClassInfo)->getRequiredProperties($type->class),
            ),
            'enum' => new EnumSchema(
                $type,
                $type->class,
                (new ClassInfo)->getClassDescription($type->class),
            ),
            'array' => new ArraySchema(
                $type,
                '',
                '',
                $this->makePropertySchema($type, 'item', 'Array item'),
            ),
            'int', 'string', 'bool', 'float' => new ScalarSchema($type, 'value', 'Correctly extracted value'),
            default => throw new \Exception('Unknown type: '.$type->type),
        };
    }

    /**
     * Makes schema for properties
     *
     * @param TypeDetails $type
     * @param string $name
     * @param string $description
     * @return Schema
     */
    protected function makePropertySchema(TypeDetails $type, string $name, string $description): Schema
    {
        return match ($type->type) {
            'object' => $this->makePropertyObject($type, $name, $description),
            'enum' => new EnumSchema($type, $name, $description),
            'array' => new ArraySchema(
                $type,
                $name,
                $description,
                $this->makePropertySchema($type->nestedType, 'item', 'Array item'),
            ),
            'int', 'string', 'bool', 'float' => new ScalarSchema($type, $name, $description),
            default => throw new \Exception('Unknown type: ' . $type->type),
        };
    }

    /**
     * Makes schema for object properties
     *
     * @param TypeDetails $type
     * @param string $name
     * @param string $description
     * @return Schema
     */
    protected function makePropertyObject(TypeDetails $type, string $name, string $description): Schema
    {
        if ($this->useObjectReferences) {
            return new ObjectRefSchema($type, $name, $description);
        }
        return new ObjectSchema(
            $type,
            $name,
            $description,
            $this->getPropertySchemas($type->class),
            (new ClassInfo)->getRequiredProperties($type->class),
        );
    }
}
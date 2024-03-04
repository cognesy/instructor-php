<?php

namespace Cognesy\Instructor\Schema\PropertyInfoBased\Factories;

use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\EnumSchema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\ObjectRefSchema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\ScalarSchema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\Schema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\TypeDetails;
use Cognesy\Instructor\Schema\PropertyInfoBased\Utils\ClassInfo;

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

    public function __construct() {}

    /**
     * Extracts the schema from a class and constructs a function call
     *
     * @param string $anyType - class name, enum name or type name
     */
    public function schema(string $anyType) : Schema
    {
        return $this->makeSchema((new TypeDetailsFactory)->fromTypeName($anyType), '', '');
    }

    /**
     * Makes schema for top level item (depending on the type)
     *
     * @param TypeDetails $type
     * @param string $name
     * @param string $description
     * @return Schema
     */
    protected function makeSchema(TypeDetails $type, string $name, string $description) : Schema
    {
        return match ($type->type) {
            'object' => new ObjectSchema(
                $type,
                $name,
                $description,
                $this->getPropertySchemas($type->class),
                (new ClassInfo)->getRequiredProperties($type->class),
            ),
            'enum' => new EnumSchema($type, $name, $description),
            'array' => new ArraySchema(
                $type,
                $name,
                $description,
                $this->makePropertySchema($type, $name, $description),
            ),
            'int', 'string', 'bool', 'float' => new ScalarSchema($type, $name, $description),
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
                $this->makePropertySchema($type->nestedType, '', ''),
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
            $propertySchemas[$property] = $this->getPropertySchema($class, $property);
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
        $propertyDescription = (new ClassInfo)->getDescription($class, $property);
        $type = (new TypeDetailsFactory)->fromPropertyInfo($propertyInfoType);
        return $this->makePropertySchema($type, $property, $propertyDescription);
    }
}
<?php

namespace Cognesy\Instructor\Schema\Factories;

use Cognesy\Instructor\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\Data\Schema\EnumSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectRefSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\ScalarSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\PropertyMap;
use Cognesy\Instructor\Schema\SchemaMap;
use Cognesy\Instructor\Schema\Utils\ClassInfo;

/**
 * Factory for creating schema objects from class names
 *
 * NOTE: Currently, OpenAI API does not work well with object references,
 * so we return the full object schema with all properties inlined.
 */
class SchemaFactory
{
    /** @var bool allows to render schema with object properties inlined or referenced */
    protected $useObjectReferences;
    protected SchemaMap $schemaMap;
    protected PropertyMap $propertyMap;
    protected TypeDetailsFactory $typeDetailsFactory;

    public function __construct(
        SchemaMap $schemaMap,
        PropertyMap $propertyMap,
        TypeDetailsFactory $typeDetailsFactory,
        bool $useObjectReferences,
    ) {
        $this->schemaMap = $schemaMap;
        $this->propertyMap = $propertyMap;
        $this->typeDetailsFactory = $typeDetailsFactory;
        $this->useObjectReferences = $useObjectReferences;
    }

    /**
     * Extracts the schema from a class and constructs a function call
     *
     * @param string $anyType - class name, enum name or type name
     */
    public function schema(string $anyType) : Schema
    {
        if (!$this->schemaMap->has($anyType)) {
            $this->schemaMap->register($anyType, $this->makeSchema($this->typeDetailsFactory->fromTypeName($anyType)));
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
        $type = $this->typeDetailsFactory->fromPropertyInfo($propertyInfoType);
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
                $type->classOnly(),
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
                $this->makePropertySchema($type, 'item', 'Correctly extract items of '.$type->nestedType->classOnly())
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
     * @return \Cognesy\Instructor\Schema\Data\Schema\Schema
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
                $this->makePropertySchema($type->nestedType, 'item', 'Correctly extract items of '.$type->nestedType->classOnly()),
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
<?php

namespace Cognesy\Instructor\Schema\Factories\Traits;

use Cognesy\Instructor\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\Data\Schema\EnumSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectRefSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\ScalarSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Utils\ClassInfo;

trait HandlesTypeDetails
{
    /**
     * Makes schema for top level item (depending on the type)
     *
     * @param TypeDetails $type
     * @param string $name
     * @param string $description
     * @return Schema
     */
    protected function makeSchema(TypeDetails $type) : Schema {
        $classInfo = match(true) {
            in_array($type->type, [TypeDetails::PHP_OBJECT, TypeDetails::PHP_ENUM], true) => new ClassInfo($type->class),
            default => null,
        };

        return match (true) {
            $classInfo && ($type->type == TypeDetails::PHP_OBJECT) => new ObjectSchema(
                type: $type,
                name: $type->classOnly(),
                description: $classInfo->getClassDescription(),
                properties: $this->getPropertySchemas($classInfo),
                required: $classInfo->getRequiredProperties(),
            ),
            $classInfo && ($type->type == TypeDetails::PHP_ENUM) => new EnumSchema(
                type: $type,
                name: $type->class,
                description: $classInfo->getClassDescription(),
            ),
            ($type->type == TypeDetails::PHP_ARRAY) => new ArraySchema(
                type: $type,
                name: '',
                description: '',
                nestedItemSchema: $this->makePropertySchema($type, 'item', 'Correctly extract items of type: '.$type->nestedType->shortName())
            ),
            in_array($type->type, TypeDetails::PHP_SCALAR_TYPES) => new ScalarSchema(
                type: $type,
                name: 'value',
                description: 'Correctly extracted value'
            ),
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
    public function makePropertySchema(TypeDetails $type, string $name, string $description): Schema {
        return match (true) {
            ($type->type == TypeDetails::PHP_OBJECT) => $this->makeObjectSchema($type, $name, $description),
            ($type->type == TypeDetails::PHP_ENUM) => new EnumSchema($type, $name, $description),
            ($type->type == TypeDetails::PHP_ARRAY) => new ArraySchema(
                $type,
                $name,
                $description,
                $this->makePropertySchema($type->nestedType, 'item', 'Correctly extract items of type: '.$type->nestedType->shortName()),
            ),
            in_array($type->type, TypeDetails::PHP_SCALAR_TYPES) => new ScalarSchema($type, $name, $description),
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
    protected function makeObjectSchema(TypeDetails $type, string $name, string $description): Schema {
        if ($this->useObjectReferences) {
            return new ObjectRefSchema($type, $name, $description);
        }
        // if references are turned off, just generate the object schema
        $classInfo = new ClassInfo($type->class);
        return new ObjectSchema(
            $type,
            $name,
            $description,
            $this->getPropertySchemas($classInfo),
            ($classInfo)->getRequiredProperties(),
        );
    }
}
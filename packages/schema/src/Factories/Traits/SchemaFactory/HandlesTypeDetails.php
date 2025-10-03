<?php declare(strict_types=1);

namespace Cognesy\Schema\Factories\Traits\SchemaFactory;

use Cognesy\Schema\Data\Schema\ArraySchema;
use Cognesy\Schema\Data\Schema\CollectionSchema;
use Cognesy\Schema\Data\Schema\EnumSchema;
use Cognesy\Schema\Data\Schema\MixedSchema;
use Cognesy\Schema\Data\Schema\ObjectRefSchema;
use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\ScalarSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Reflection\ClassInfo;

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
            $type->hasClass() => ClassInfo::fromString($type->class() ?? ''),
            default => null,
        };

        return match (true) {
            $type->isObject() && $classInfo !== null => new ObjectSchema(
                type: $type,
                name: $type->classOnly(),
                description: $classInfo->getClassDescription(),
                properties: $this->getPropertySchemas($classInfo),
                required: $classInfo->getRequiredProperties(),
            ),
            $type->isObject() && !$type->hasClass() => new ObjectSchema(
                type: $type,
                name: $type->classOnly(),
                description: '',
                properties: [],
                required: [],
            ),
            $type->isEnum() => new EnumSchema(
                type: $type,
                name: $type->class() ?? '',
                description: $classInfo?->getClassDescription() ?? '',
            ),
            $type->isCollection() => new CollectionSchema(
                type: $type,
                name: '',
                description: '',
                nestedItemSchema: $this->makePropertySchema($type, 'item', 'Correctly extract items of type: '.($type->nestedType?->shortName() ?? 'mixed'))
            ),
            $type->isArray() => new ArraySchema(
                type: $type,
                name: '',
                description: ''
            ),
            $type->isScalar() => new ScalarSchema(
                type: $type,
                name: 'value',
                description: 'Correctly extracted value'
            ),
            $type->isMixed() => new MixedSchema(
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
     * @return Schema
     */
    protected function makePropertySchema(TypeDetails $type, string $name, string $description): Schema {
        return match (true) {
            $type->isEnum() => new EnumSchema($type, $name, $description),
            $type->isObject() => $this->makeObjectSchema($type, $name, $description),
            $type->isCollection() => new CollectionSchema(
                $type,
                $name,
                $description,
                $this->makeNestedItemSchema(
                    type: $type->nestedType ?? TypeDetails::mixed(),
                    name: 'item',
                    description: 'Correctly extract items of type: ' . ($type->nestedType?->shortName() ?? 'mixed')
                ),
            ),
            $type->isScalar() => new ScalarSchema($type, $name, $description),
            $type->isArray() => new ArraySchema($type, $name, $description),
            $type->isMixed() => new MixedSchema($type, $name, $description),
            default => throw new \Exception('Unknown type: ' . $type->toString()),
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
        $classInfo = ClassInfo::fromString($type->class() ?? throw new \Exception('Object type must have a class'));
        return new ObjectSchema(
            $type,
            $name,
            $description,
            $this->getPropertySchemas($classInfo),
            $classInfo->getRequiredProperties(),
        );
    }

    /**
     * Makes schema for collection nested items
     *
     * @param TypeDetails $type
     * @param string $name
     * @param string $description
     * @return Schema
     */
    protected function makeNestedItemSchema(TypeDetails $type, string $name, string $description): Schema {
        return match (true) {
            $type->isObject() => $this->makeObjectSchema($type, $name, $description),
            $type->isEnum() => new EnumSchema($type, $name, $description),
            $type->isCollection() => throw new \Exception('Collections are not allowed as collection nested items'),
            $type->isArray() => throw new \Exception('Arrays are not allowed as collection nested items'),
            $type->isScalar() => new ScalarSchema($type, $name, $description),
            $type->isMixed() => new MixedSchema($type, $name, $description),
            default => throw new \Exception('Unknown type: ' . $type->toString()),
        };
    }
}
<?php
namespace Cognesy\Instructor\Extras\Structure\Traits\Field;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Features\Schema\Data\TypeDetails;
use Cognesy\Instructor\Features\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Features\Schema\Factories\TypeDetailsFactory;
use DateTime;

trait HandlesFieldDefinitions
{
    private TypeDetailsFactory $typeDetailsFactory;

    static public function int(string $name, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->scalarType(TypeDetails::PHP_INT);
        return new Field($name, $description, $type);
    }

    static public function string(string $name, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->scalarType(TypeDetails::PHP_STRING);
        return new Field($name, $description, $type);
    }

    static public function float(string $name, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->scalarType(TypeDetails::PHP_FLOAT);
        return new Field($name, $description, $type);
    }

    static public function bool(string $name, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->scalarType(TypeDetails::PHP_BOOL);
        return new Field($name, $description, $type);
    }

    static public function enum(string $name, string $enumClass, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->enumType($enumClass);
        return new Field($name, $description, $type);
    }

    static public function option(string $name, array $values, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->optionType($values);
        return new Field($name, $description, $type);
    }

    static public function object(string $name, string $class, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->objectType($class);
        return new Field($name, $description, $type);
    }

    static public function datetime(string $name, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->objectType(DateTime::class);
        return new Field($name, $description, $type);
    }

    static public function structure(string $name, array|callable $fields, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $structure = Structure::define($name, $fields, $description);
        $result = new Field($name, $description, $factory->objectType(Structure::class));
        $result->set($structure);
        return $result;
    }

    static public function collection(string $name, string|object $itemType, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $schemaFactory = new SchemaFactory();
        return match(true) {
            is_string($itemType) => new Field($name, $description, $factory->collectionType($itemType)),
            is_object($itemType) && $itemType instanceof TypeDetails => (new Field(
                $name,
                $description,
                $factory->collectionType($itemType->toString())
            ))->set($itemType),
            is_object($itemType) && $itemType instanceof Structure => (new Field(
                name: $name,
                description: $description,
                typeDetails: $factory->collectionType(Structure::class),
                customSchema: $schemaFactory->collection(
                    nestedType: Structure::class,
                    name: $name,
                    description: $description,
                    nestedTypeSchema: $itemType->schema(),
                ),
                prototype: $itemType,
            )),
            default => throw new \InvalidArgumentException('Invalid item type for collection field: ' . get_debug_type($name)),
        };
    }

    static public function array(string $name, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->arrayType();
        return new Field($name, $description, $type);
    }
}
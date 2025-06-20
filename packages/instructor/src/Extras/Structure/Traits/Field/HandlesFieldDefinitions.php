<?php
namespace Cognesy\Instructor\Extras\Structure\Traits\Field;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Factories\SchemaFactory;
use DateTime;

trait HandlesFieldDefinitions
{
    static public function int(string $name, string $description = '') : self {
        return new Field($name, $description, TypeDetails::int());
    }

    static public function string(string $name, string $description = '') : self {
        return new Field($name, $description, TypeDetails::string());
    }

    static public function float(string $name, string $description = '') : self {
        return new Field($name, $description, TypeDetails::float());
    }

    static public function bool(string $name, string $description = '') : self {
        return new Field($name, $description, TypeDetails::bool());
    }

    static public function enum(string $name, string $enumClass, string $description = '') : self {
        return new Field($name, $description, TypeDetails::enum($enumClass));
    }

    static public function option(string $name, array $values, string $description = '') : self {
        return new Field($name, $description, TypeDetails::option($values));
    }

    static public function object(string $name, string $class, string $description = '') : self {
        return new Field($name, $description, TypeDetails::object($class));
    }

    static public function datetime(string $name, string $description = '') : self {
        return new Field($name, $description, TypeDetails::object(DateTime::class));
    }

    static public function structure(string $name, array|callable $fields, string $description = '') : self {
        $structure = Structure::define($name, $fields, $description);
        $result = new Field($name, $description, TypeDetails::object(Structure::class));
        $result->set($structure);
        return $result;
    }

    static public function collection(string $name, string|object $itemType, string $description = '') : self {
        $schemaFactory = new SchemaFactory();
        return match(true) {
            is_string($itemType) => new Field(
                name: $name,
                description: $description,
                typeDetails: TypeDetails::collection($itemType)
            ),
            is_object($itemType) && $itemType instanceof TypeDetails => (new Field(
                name: $name,
                description: $description,
                typeDetails: TypeDetails::collection($itemType->toString())))->set($itemType),
            is_object($itemType) && $itemType instanceof Structure => (new Field(
                name: $name,
                description: $description,
                typeDetails: TypeDetails::collection(Structure::class),
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
        return new Field($name, $description, TypeDetails::array());
    }
}
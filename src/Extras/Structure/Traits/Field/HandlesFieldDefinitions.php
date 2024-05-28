<?php
namespace Cognesy\Instructor\Extras\Structure\Traits\Field;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use DateTime;

trait HandlesFieldDefinitions
{
    private TypeDetailsFactory $typeDetailsFactory;

    static public function int(string $name, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->scalarType(TypeDetails::PHP_INT);
        return new \Cognesy\Instructor\Extras\Structure\Field($name, $description, $type);
    }

    static public function string(string $name, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->scalarType(TypeDetails::PHP_STRING);
        return new \Cognesy\Instructor\Extras\Structure\Field($name, $description, $type);
    }

    static public function float(string $name, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->scalarType(TypeDetails::PHP_FLOAT);
        return new \Cognesy\Instructor\Extras\Structure\Field($name, $description, $type);
    }

    static public function bool(string $name, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->scalarType(TypeDetails::PHP_BOOL);
        return new \Cognesy\Instructor\Extras\Structure\Field($name, $description, $type);
    }

    static public function enum(string $name, string $enumClass, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->enumType($enumClass);
        return new \Cognesy\Instructor\Extras\Structure\Field($name, $description, $type);
    }

    static public function object(string $name, string $class, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->objectType($class);
        return new \Cognesy\Instructor\Extras\Structure\Field($name, $description, $type);
    }

    static public function datetime(string $name, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->objectType(DateTime::class);
        return new \Cognesy\Instructor\Extras\Structure\Field($name, $description, $type);
    }

    static public function structure(string $name, array|callable $fields, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->objectType(Structure::class);
        $result = new \Cognesy\Instructor\Extras\Structure\Field($name, $description, $type);
        $result->value = Structure::define($name, $fields, $description);
        return $result;
    }

    static public function array(string $name, string $itemType, string $description = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->arrayType($itemType);
        return new Field($name, $description, $type);
    }
}
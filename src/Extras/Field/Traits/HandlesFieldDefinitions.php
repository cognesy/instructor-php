<?php
namespace Cognesy\Instructor\Extras\Field\Traits;

use Cognesy\Instructor\Extras\Field\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use DateTime;
use Exception;
use Symfony\Component\PropertyInfo\Type;

trait HandlesFieldDefinitions
{
    private TypeDetailsFactory $typeDetailsFactory;

    static public function fromPropertyInfoType(string $name, Type $type, string $description = '', string $instructions = '') : self {
        $typeDetails = (new TypeDetailsFactory)->fromPropertyInfo($type);
        return Field::fromTypeDetails($name, $typeDetails, $description, $instructions);
    }

    static public function fromTypeName(string $name, string $typeName, string $description = '', string $instructions = '') : self {
        $typeDetails = (new TypeDetailsFactory)->fromTypeName($typeName);
        return Field::fromTypeDetails($name, $typeDetails, $description, $instructions);
    }

    static public function fromTypeDetails(string $name, TypeDetails $typeDetails, string $description = '', string $instructions = '') : self {
        return match($typeDetails->type) {
            TypeDetails::PHP_INT => self::int($name, $description, $instructions),
            TypeDetails::PHP_STRING => self::string($name, $description, $instructions),
            TypeDetails::PHP_FLOAT => self::float($name, $description, $instructions),
            TypeDetails::PHP_BOOL => self::bool($name, $description, $instructions),
            TypeDetails::PHP_ENUM => self::enum($name, $typeDetails->class, $description, $instructions),
            TypeDetails::PHP_ARRAY => self::array($name, $typeDetails->nestedType, $description, $instructions),
            TypeDetails::PHP_OBJECT => self::object($name, $typeDetails->class, $description, $instructions),
            TypeDetails::PHP_MIXED => self::string($name, $description, $instructions),
            default => throw new Exception('Unsupported type: '.$typeDetails->type),
        };
    }

    static public function int(string $name, string $description = '', string $instructions = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->scalarType(TypeDetails::PHP_INT);
        return new Field($name, $description, $instructions, $type);
    }

    static public function string(string $name, string $description = '', string $instructions = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->scalarType(TypeDetails::PHP_STRING);
        return new Field($name, $description, $instructions, $type);
    }

    static public function float(string $name, string $description = '', string $instructions = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->scalarType(TypeDetails::PHP_FLOAT);
        return new Field($name, $description, $instructions, $type);
    }

    static public function bool(string $name, string $description = '', string $instructions = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->scalarType(TypeDetails::PHP_BOOL);
        return new Field($name, $description, $instructions, $type);
    }

    static public function enum(string $name, string $enumClass, string $description = '', string $instructions = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->enumType($enumClass);
        return new Field($name, $description, $instructions, $type);
    }

    static public function object(string $name, string $class, string $description = '', string $instructions = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->objectType($class);
        return new Field($name, $description, $instructions, $type);
    }

    static public function datetime(string $name, string $description = '', string $instructions = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->objectType(DateTime::class);
        return new Field($name, $description, $instructions, $type);
    }

    static public function structure(string $name, array|callable $fields, string $description = '', string $instructions = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->objectType(Structure::class);
        $result = new Field($name, $description, $instructions, $type);
        $result->value = Structure::define($name, $fields, $description, $instructions);
        return $result;
    }

    static public function array(string $name, string $itemType, string $description = '', string $instructions = '') : self {
        $factory = new TypeDetailsFactory();
        $type = $factory->arrayType($itemType);
        return new Field($name, $description, $instructions, $type);
    }
}
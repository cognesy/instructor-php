<?php declare(strict_types=1);

namespace Cognesy\Schema\Data\Traits\TypeDetails;

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Factories\TypeDetailsFactory;

trait HandlesFactoryMethods
{
    // TYPES

    /**
     * @param class-string $class
     */
    static public function object(string $class) : TypeDetails {
        return (new TypeDetailsFactory)->objectType($class);
    }

    /**
     * @param class-string $class
     */
    static public function enum(string $class, ?string $backingType = null, ?array $values = null) : TypeDetails {
        return (new TypeDetailsFactory)->enumType($class, $backingType, $values);
    }

    static public function option(array $values) : TypeDetails {
        return (new TypeDetailsFactory)->optionType($values);
    }

    static public function collection(string $itemType) : TypeDetails {
        return (new TypeDetailsFactory)->collectionType($itemType);
    }

    static public function array() : TypeDetails {
        return (new TypeDetailsFactory)->arrayType();
    }

    static public function int() : TypeDetails {
        return (new TypeDetailsFactory)->scalarType(TypeDetails::PHP_INT);
    }

    static public function string() : TypeDetails {
        return (new TypeDetailsFactory)->scalarType(TypeDetails::PHP_STRING);
    }

    static public function bool() : TypeDetails {
        return (new TypeDetailsFactory)->scalarType(TypeDetails::PHP_BOOL);
    }

    static public function float() : TypeDetails {
        return (new TypeDetailsFactory)->scalarType(TypeDetails::PHP_FLOAT);
    }

    static public function mixed() : TypeDetails {
        return (new TypeDetailsFactory)->mixedType();
    }

    // MISC

    static public function fromTypeName(string $type) : TypeDetails {
        return (new TypeDetailsFactory)->fromTypeName($type);
    }

    public static function fromPhpDocTypeString(string $typeString) : TypeDetails {
        return (new TypeDetailsFactory)->fromPhpDocTypeString($typeString);
    }

//    public static function fromJsonSchema(JsonSchema $jsonSchema) : TypeDetails {
//        return (new TypeDetailsFactory)->fromJsonSchema($jsonSchema);
//    }

    static public function fromValue(mixed $value) : TypeDetails {
        return (new TypeDetailsFactory)->fromValue($value);
    }

    static public function scalar(string $type) : TypeDetails {
        return (new TypeDetailsFactory)->scalarType($type);
    }

    static public function undefined() : TypeDetails {
        return new self(self::PHP_UNSUPPORTED);
    }
}
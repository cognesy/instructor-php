<?php

namespace Cognesy\Instructor\Features\Schema\Data\Traits\TypeDetails;

use Cognesy\Instructor\Features\Schema\Data\TypeDetails;
use Cognesy\Instructor\Features\Schema\Factories\TypeDetailsFactory;
use Symfony\Component\PropertyInfo\Type;

trait HandlesFactoryMethods
{
    static public function undefined() : TypeDetails {
        return new self(self::PHP_UNSUPPORTED);
    }

    static public function fromTypeName(string $type) : TypeDetails {
        return (new TypeDetailsFactory)->fromTypeName($type);
    }

    //static public function fromJsonTypeName(string $jsonType, ?array $details = null) : TypeDetails {
    //    $phpType = TypeDetails::toPhpType($jsonType);
    //    return (new TypeDetailsFactory)->fromTypeName($phpType);
    //}

    static public function fromPropertyInfoType(Type $type) : TypeDetails {
        return (new TypeDetailsFactory)->fromPropertyInfo($type);
    }

    static public function object(string $class) : TypeDetails {
        return (new TypeDetailsFactory)->objectType($class);
    }

    static public function enum(string $class) : TypeDetails {
        return (new TypeDetailsFactory)->enumType($class);
    }

    static public function collection(TypeDetails $nestedType) : TypeDetails {
        return (new TypeDetailsFactory)->collectionType($nestedType);
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
}
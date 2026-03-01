<?php declare(strict_types=1);

namespace Cognesy\Schema\Exceptions;

use RuntimeException;

final class TypeResolutionException extends RuntimeException
{
    public static function emptyTypeSpecification() : self {
        return new self('Type specification cannot be empty');
    }

    public static function missingObjectClass(string $sourceType) : self {
        return new self('Object type must have a class name: ' . $sourceType);
    }

    public static function missingEnumClass(string $sourceType) : self {
        return new self('Enum type must have a class: ' . $sourceType);
    }

    public static function unsupportedType(string $sourceType) : self {
        return new self('Unsupported type: ' . $sourceType);
    }

    public static function unsupportedUnion(string $sourceType) : self {
        return new self('Union types with multiple non-null branches are not supported: ' . $sourceType);
    }
}

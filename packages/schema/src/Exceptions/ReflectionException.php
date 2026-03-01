<?php declare(strict_types=1);

namespace Cognesy\Schema\Exceptions;

use RuntimeException;

final class ReflectionException extends RuntimeException
{
    public static function classNotFound(string $class) : self {
        return new self("Cannot create ClassInfo for `$class`");
    }

    public static function propertyNotFound(string $property, string $class) : self {
        return new self("Property `$property` not found in class `$class`.");
    }

    public static function invalidFilter() : self {
        return new self('Filter must be a callable.');
    }

    public static function unsupportedCallable(string $type) : self {
        return new self('Unsupported callable type: ' . $type);
    }

    public static function parameterNotFound(string $name, string $functionName) : self {
        return new self("Parameter `$name` not found in function `$functionName`.");
    }
}

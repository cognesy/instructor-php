<?php declare(strict_types=1);

namespace Cognesy\Schema\Exceptions;

use RuntimeException;

final class SchemaParsingException extends RuntimeException
{
    public static function forRootType(string $type) : self {
        return new self("Root JSON Schema must be an object, got: {$type}");
    }

    public static function forMissingCollectionItems() : self {
        return new self('Collection must define `items` schema');
    }

    public static function forUnresolvableRootSchema() : self {
        return new self('Root JSON Schema could not be resolved to object-like schema');
    }
}

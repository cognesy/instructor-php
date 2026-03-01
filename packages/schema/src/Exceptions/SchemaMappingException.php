<?php declare(strict_types=1);

namespace Cognesy\Schema\Exceptions;

use RuntimeException;

final class SchemaMappingException extends RuntimeException
{
    public static function unknownSchemaType(string $type) : self {
        return new self('Unknown type: ' . $type);
    }

    public static function missingObjectClass() : self {
        return new self('Object type must have a class');
    }

    public static function invalidCollectionNestedType(string $type) : self {
        return new self('Collections cannot contain nested type: ' . $type);
    }
}

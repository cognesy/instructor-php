<?php

namespace Cognesy\Instructor\Extras\Structure;

use Cognesy\Instructor\Features\Schema\Data\TypeDetails;
use Exception;

class FieldFactory
{
    public static function fromTypeName(string $name, string $typeName, string $description = ''): Field {
        $typeDetails = TypeDetails::fromTypeName($typeName);
        return FieldFactory::fromTypeDetails($name, $typeDetails, $description);
    }

    public static function fromTypeDetails(string $name, TypeDetails $typeDetails, string $description = ''): Field {
        return match ($typeDetails->type) {
            TypeDetails::PHP_INT => Field::int($name, $description),
            TypeDetails::PHP_STRING => Field::string($name, $description),
            TypeDetails::PHP_FLOAT => Field::float($name, $description),
            TypeDetails::PHP_BOOL => Field::bool($name, $description),
            TypeDetails::PHP_ENUM => Field::enum($name, $typeDetails->class, $description),
            TypeDetails::PHP_COLLECTION => Field::collection($name, $typeDetails->nestedType, $description),
            TypeDetails::PHP_ARRAY => Field::array($name, $description),
            TypeDetails::PHP_OBJECT => Field::object($name, $typeDetails->class, $description),
            TypeDetails::PHP_MIXED => Field::string($name, $description),
            default => throw new Exception('Unsupported type: ' . $typeDetails->type),
        };
    }
}
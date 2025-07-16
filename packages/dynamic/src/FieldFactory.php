<?php declare(strict_types=1);

namespace Cognesy\Dynamic;

use Cognesy\Schema\Data\TypeDetails;
use Exception;

class FieldFactory
{
    public static function fromTypeName(string $name, string $typeName, string $description = ''): Field {
        $typeDetails = TypeDetails::fromTypeName($typeName);
        return FieldFactory::fromTypeDetails($name, $typeDetails, $description);
    }

    public static function fromTypeDetails(string $name, TypeDetails $typeDetails, string $description = ''): Field {
        return match (true) {
            $typeDetails->isInt() => Field::int($name, $description),
            $typeDetails->isString() => Field::string($name, $description),
            $typeDetails->isFloat() => Field::float($name, $description),
            $typeDetails->isBool() => Field::bool($name, $description),
            $typeDetails->isEnum() => Field::enum($name, $typeDetails->class, $description),
            $typeDetails->isCollection() => Field::collection($name, $typeDetails->nestedType, $description),
            $typeDetails->isArray() => Field::array($name, $description),
            $typeDetails->isObject() => Field::object($name, $typeDetails->class, $description),
            $typeDetails->isMixed() => Field::string($name, $description),
            default => throw new Exception('Unsupported type: ' . $typeDetails->type),
        };
    }
}
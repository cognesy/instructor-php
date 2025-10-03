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
        if ($typeDetails->isEnum() && $typeDetails->class === null) {
            throw new Exception('Enum type requires a valid class-string.');
        }
        if ($typeDetails->isObject() && $typeDetails->class === null) {
            throw new Exception('Object type requires a valid class-string.');
        }
        return match (true) {
            $typeDetails->isInt() => Field::int($name, $description),
            $typeDetails->isString() => Field::string($name, $description),
            $typeDetails->isFloat() => Field::float($name, $description),
            $typeDetails->isBool() => Field::bool($name, $description),
            $typeDetails->isEnum() => Field::enum($name, $typeDetails->class ?? throw new Exception('Enum class cannot be null'), $description),
            $typeDetails->isCollection() => Field::collection($name, $typeDetails->nestedType?->toString() ?? 'mixed', $description),
            $typeDetails->isArray() => Field::array($name, $description),
            $typeDetails->isObject() => Field::object($name, $typeDetails->class ?? throw new Exception('Object class cannot be null'), $description),
            $typeDetails->isMixed() => Field::string($name, $description),
            default => throw new Exception('Unsupported type: ' . $typeDetails->type),
        };
    }
}

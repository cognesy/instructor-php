<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\Utils;

use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\PhpType;
use ReflectionClass;
use ReflectionEnum;
use ReflectionType;

class ReflectionUtils {
    static public function getType(ReflectionType $type) : PhpType {
        if ($type === null) {
            return PhpType::UNDEFINED;
        }
        $typeName = $type->getName();
        if ($type->isBuiltin() && in_array($typeName, ['int', 'float', 'string', 'bool', 'array'])) {
            return PhpType::fromReflectionType($type);
        }
        $class = new ReflectionClass($type->getName());
        if ($class->isEnum()) {
            return PhpType::ENUM;
        }
        return PhpType::OBJECT;
    }

    public static function getEnumValues(ReflectionEnum $class): array
    {
        $isBacked = $class->isBacked();
        return match ($isBacked) {
            true => (new ReflectionUtils)->getBackedEnumValues($class),
            false => (new ReflectionUtils)->getNonBackedEnumValues($class),
        };
    }

    private function getBackedEnumValues(ReflectionEnum $class): array
    {
        $values = [];
        $constants = $class->getReflectionConstants();
        foreach ($constants as $constant) {
            $values[] = $constant->getValue()->value;
        }
        return $values;
    }

    private function getNonBackedEnumValues(ReflectionEnum $class): array
    {
        $values = [];
        $constants = $class->getReflectionConstants();
        foreach ($constants as $constant) {
            $values[] = $constant->getName();
        }
        return $values;
    }
}

<?php

namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\Factories;

use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\PhpType;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\ArrayPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\BooleanPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\EnumPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\FloatPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\IntegerPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\ObjectPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\PropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\StringPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Utils\ReflectionUtils;
use Exception;
use ReflectionProperty;

class PropertyDataFactory
{
    static public function make(ReflectionProperty $property) : PropertyData
    {
        return (new PropertyDataFactory())->makeAny($property);
    }

    protected function makeAny(ReflectionProperty $property) : PropertyData
    {
        return match ($this->getType($property)) {
            PhpType::STRING => new StringPropertyData($property),
            PhpType::INTEGER => new IntegerPropertyData($property),
            PhpType::FLOAT => new FloatPropertyData($property),
            PhpType::BOOLEAN => new BooleanPropertyData($property),
            PhpType::OBJECT => new ObjectPropertyData($property),
            PhpType::ENUM => new EnumPropertyData($property),
            PhpType::ARRAY => new ArrayPropertyData($property),
            default => throw new Exception('Unsupported type: ' . $property->getType()?->getName())
        };
    }

    protected function getType(ReflectionProperty $property) : PhpType
    {
        $type = $property->getType();
        if ($type === null) {
            throw new Exception('Property type is not set: ' . $property->getName());
        }
        return ReflectionUtils::getType($type);
    }
}
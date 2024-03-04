<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\Factories;

use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\PhpType;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData\ArrayParameterData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData\BooleanParameterData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData\EnumParameterData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData\FloatParameterData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData\IntegerParameterData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData\ObjectParameterData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData\ParameterData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData\StringParameterData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData\UndefinedParameterData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\ArrayPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\BooleanPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\EnumPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\FloatPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\IntegerPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\ObjectPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\PropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\StringPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData\UndefinedPropertyData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\TypeDefs\TypeDef;

class ArrayItemFactory
{
    static public function makePropertyData(TypeDef $typeDef): PropertyData
    {
        return match ($typeDef->type) {
            PhpType::STRING => StringPropertyData::asArrayItem($typeDef),
            PhpType::INTEGER => IntegerPropertyData::asArrayItem($typeDef),
            PhpType::FLOAT => FloatPropertyData::asArrayItem($typeDef),
            PhpType::BOOLEAN => BooleanPropertyData::asArrayItem($typeDef),
            PhpType::OBJECT => ObjectPropertyData::asArrayItem($typeDef),
            PhpType::ENUM => EnumPropertyData::asArrayItem($typeDef),
            PhpType::ARRAY => ArrayPropertyData::asArrayItem($typeDef),
            default => UndefinedPropertyData::asArrayItem($typeDef),
        };
    }

    static public function makeParameterData(TypeDef $typeDef): ParameterData
    {
        return match ($typeDef->type) {
            PhpType::STRING => StringParameterData::asArrayItem($typeDef),
            PhpType::INTEGER => IntegerParameterData::asArrayItem($typeDef),
            PhpType::FLOAT => FloatParameterData::asArrayItem($typeDef),
            PhpType::BOOLEAN => BooleanParameterData::asArrayItem($typeDef),
            PhpType::OBJECT => ObjectParameterData::asArrayItem($typeDef),
            PhpType::ENUM => EnumParameterData::asArrayItem($typeDef),
            PhpType::ARRAY => ArrayParameterData::asArrayItem($typeDef),
            default => UndefinedParameterData::asArrayItem($typeDef),
        };
    }
}
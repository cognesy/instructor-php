<?php
namespace Cognesy\Instructor\Reflection\Factories;

use Cognesy\Instructor\Reflection\Enums\PhpType;
use Cognesy\Instructor\Reflection\ParameterData\ArrayParameterData;
use Cognesy\Instructor\Reflection\ParameterData\BooleanParameterData;
use Cognesy\Instructor\Reflection\ParameterData\EnumParameterData;
use Cognesy\Instructor\Reflection\ParameterData\FloatParameterData;
use Cognesy\Instructor\Reflection\ParameterData\IntegerParameterData;
use Cognesy\Instructor\Reflection\ParameterData\ObjectParameterData;
use Cognesy\Instructor\Reflection\ParameterData\ParameterData;
use Cognesy\Instructor\Reflection\ParameterData\StringParameterData;
use Cognesy\Instructor\Reflection\ParameterData\UndefinedParameterData;
use Cognesy\Instructor\Reflection\PropertyData\ArrayPropertyData;
use Cognesy\Instructor\Reflection\PropertyData\BooleanPropertyData;
use Cognesy\Instructor\Reflection\PropertyData\EnumPropertyData;
use Cognesy\Instructor\Reflection\PropertyData\FloatPropertyData;
use Cognesy\Instructor\Reflection\PropertyData\IntegerPropertyData;
use Cognesy\Instructor\Reflection\PropertyData\ObjectPropertyData;
use Cognesy\Instructor\Reflection\PropertyData\PropertyData;
use Cognesy\Instructor\Reflection\PropertyData\StringPropertyData;
use Cognesy\Instructor\Reflection\PropertyData\UndefinedPropertyData;
use Cognesy\Instructor\Reflection\TypeDefs\TypeDef;

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
<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData;

use Cognesy\Instructor\Schema\ReflectionBased\Data\FCAtom;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\JsonType;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\PhpType;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\TypeDefs\TypeDef;

class BooleanPropertyData extends PropertyData {
    public PhpType $type = PhpType::BOOLEAN;

    public function toStruct(): FCAtom {
        $fcAtom = new FCAtom();
        $fcAtom->name = $this->name;
        $fcAtom->type = JsonType::BOOLEAN->value;
        $fcAtom->description = $this->description;
        return $fcAtom;
    }

    public static function asArrayItem(TypeDef $typeDef) : BooleanPropertyData {
        $itemType = new BooleanPropertyData();
        $itemType->name = 'items';
        $itemType->type = PhpType::BOOLEAN;
        return $itemType;
    }
}
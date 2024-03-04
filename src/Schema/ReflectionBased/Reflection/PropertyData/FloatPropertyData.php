<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData;

use Cognesy\Instructor\Schema\ReflectionBased\Data\FCAtom;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\JsonType;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\PhpType;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\TypeDefs\TypeDef;

class FloatPropertyData extends PropertyData {
    public PhpType $type = PhpType::FLOAT;

    public function toStruct(): FCAtom {
        $fcAtom = new FCAtom();
        $fcAtom->name = $this->name;
        $fcAtom->type = JsonType::NUMBER->value;
        $fcAtom->description = $this->description;
        return $fcAtom;
    }

    public static function asArrayItem(TypeDef $typeDef) : FloatPropertyData {
        $itemType = new FloatPropertyData();
        $itemType->name = 'items';
        $itemType->type = PhpType::FLOAT;
        return $itemType;
    }
}

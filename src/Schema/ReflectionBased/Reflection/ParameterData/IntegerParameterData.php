<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData;

use Cognesy\Instructor\Schema\ReflectionBased\Data\FCAtom;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\JsonType;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\PhpType;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\TypeDefs\TypeDef;

class IntegerParameterData extends ParameterData {
    public function toStruct(): FCAtom {
        $fcAtom = new FCAtom();
        $fcAtom->name = $this->name;
        $fcAtom->type = JsonType::INTEGER->value;
        $fcAtom->description = $this->description;
        return $fcAtom;
    }

    public static function asArrayItem(TypeDef $typeDef) : IntegerParameterData {
        $itemType = new IntegerParameterData(null);
        $itemType->name = 'items';
        $itemType->type = PhpType::INTEGER;
        return $itemType;
    }
}

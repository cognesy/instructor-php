<?php
namespace Cognesy\Instructor\Reflection\ParameterData;

use Cognesy\Instructor\Schema\FCAtom;
use Cognesy\Instructor\Reflection\Enums\JsonType;
use Cognesy\Instructor\Reflection\Enums\PhpType;
use Cognesy\Instructor\Reflection\TypeDefs\TypeDef;

class UndefinedParameterData extends ParameterData {
    public function toStruct(): FCAtom {
        $fcAtom = new FCAtom();
        $fcAtom->name = $this->name;
        $fcAtom->type = JsonType::STRING->value;
        $fcAtom->description = $this->description;
        return $fcAtom;
    }

    static public function asArrayItem(TypeDef $typeDef) : UndefinedParameterData {
        $itemType = new UndefinedParameterData(null);
        $itemType->name = 'items';
        $itemType->type = PhpType::UNDEFINED;
        return $itemType;
    }
}

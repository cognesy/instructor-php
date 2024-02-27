<?php
namespace Cognesy\Instructor\Reflection\PropertyData;

use Cognesy\Instructor\Schema\FCAtom;
use Cognesy\Instructor\Reflection\Enums\JsonType;
use Cognesy\Instructor\Reflection\Enums\PhpType;
use Cognesy\Instructor\Reflection\TypeDefs\TypeDef;

class UndefinedPropertyData extends PropertyData {
    public PhpType $type = PhpType::UNDEFINED;

    public function toStruct(): FCAtom {
        $fcAtom = new FCAtom();
        $fcAtom->name = $this->name;
        $fcAtom->type = JsonType::STRING->value;
        $fcAtom->description = $this->description;
        return $fcAtom;
    }

    static public function asArrayItem(TypeDef $typeDef) : UndefinedPropertyData {
        $itemType = new UndefinedPropertyData(null);
        $itemType->name = 'items';
        $itemType->type = PhpType::UNDEFINED;
        return $itemType;
    }
}

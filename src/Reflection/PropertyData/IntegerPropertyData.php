<?php
namespace Cognesy\Instructor\Reflection\PropertyData;

use Cognesy\Instructor\Schema\FCAtom;
use Cognesy\Instructor\Reflection\Enums\JsonType;
use Cognesy\Instructor\Reflection\Enums\PhpType;
use Cognesy\Instructor\Reflection\TypeDefs\TypeDef;

class IntegerPropertyData extends PropertyData {
    public PhpType $type = PhpType::INTEGER;

    public function toStruct(): FCAtom {
        $fcAtom = new FCAtom();
        $fcAtom->name = $this->name;
        $fcAtom->type = JsonType::INTEGER->value;
        $fcAtom->description = $this->description;
        return $fcAtom;
    }

    public static function asArrayItem(TypeDef $typeDef) : IntegerPropertyData {
        $itemType = new IntegerPropertyData();
        $itemType->name = 'items';
        $itemType->type = PhpType::INTEGER;
        return $itemType;
    }
}

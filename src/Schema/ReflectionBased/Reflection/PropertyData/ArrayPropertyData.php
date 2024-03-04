<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData;

use Cognesy\Instructor\Schema\ReflectionBased\Data\FCArray;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\PhpType;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Factories\ArrayItemFactory;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\TypeDefs\TypeDef;
use Exception;
use ReflectionProperty;

class ArrayPropertyData extends PropertyData {
    public PhpType $type = PhpType::ARRAY;

    public ?PropertyData $itemType = null;

    protected function getPropertyData(ReflectionProperty $property) : void {
        parent::getPropertyData($property);
        $this->itemType = ArrayItemFactory::makePropertyData($this->typeDef->valueType);
    }

    public function toStruct() : FCArray {
        $fcArray = new FCArray();
        $fcArray->name = $this->name;
        $fcArray->description = $this->description;
        $fcArray->itemType = $this->itemType->toStruct();
        return $fcArray;
    }

    public static function asArrayItem(TypeDef $typeDef) : ArrayPropertyData {
        throw new Exception('Arrays are not supported as array items');
    }
}

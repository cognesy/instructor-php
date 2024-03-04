<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData;

use Cognesy\Instructor\Schema\ReflectionBased\Data\FCArray;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Factories\ArrayItemFactory;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\TypeDefs\TypeDef;
use Exception;
use ReflectionParameter;

class ArrayParameterData extends ParameterData {
    public ?ParameterData $itemType = null;

    protected function getParameterData(ReflectionParameter $parameter) : void {
        parent::getParameterData($parameter);
        $this->itemType = ArrayItemFactory::makeParameterData($this->typeDef->valueType);
    }

    public function toStruct() : FCArray {
        $fcArray = new FCArray();
        $fcArray->name = $this->name;
        $fcArray->description = $this->description;
        $fcArray->itemType = $this->itemType->toStruct();
        return $fcArray;
    }

    public static function asArrayItem(TypeDef $typeDef) : ArrayParameterData {
        throw new Exception('Arrays are not supported as array items');
    }
}

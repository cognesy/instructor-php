<?php
namespace Cognesy\Instructor\Reflection\ParameterData;

use Cognesy\Instructor\Schema\FCArray;
use Cognesy\Instructor\Reflection\Factories\ArrayItemFactory;
use Exception;
use ReflectionParameter;
use Cognesy\Instructor\Reflection\TypeDefs\TypeDef;

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

<?php
namespace Cognesy\Instructor\Reflection\PropertyData;

use Cognesy\Instructor\Schema\FCObject;
use Cognesy\Instructor\Reflection\ClassData;
use Cognesy\Instructor\Reflection\Enums\PhpType;
use Cognesy\Instructor\Reflection\TypeDefs\TypeDef;
use Exception;
use ReflectionClass;
use ReflectionProperty;

class ObjectPropertyData extends PropertyData {
    public PhpType $type = PhpType::OBJECT;

    public ?ClassData $classData = null;

    protected function getPropertyData(ReflectionProperty $property) : void {
        parent::getPropertyData($property);
        // Get the class data for the property
        $type = $property->getType();
        if (!$type) {
            throw new Exception('Property type is not defined');
        }
        $class = new ReflectionClass($type->getName());
        $this->classData = new ClassData($class);
    }

    public function toStruct() : FCObject {
        return $this->classData->toStruct($this->name, $this->description);
    }

    public static function asArrayItem(TypeDef $typeDef) : ObjectPropertyData {
        $itemType = new ObjectPropertyData();
        $itemType->name = 'items';
        $itemType->type = PhpType::OBJECT;
        $class = new ReflectionClass($typeDef->className);
        $itemType->classData = new ClassData($class);
        return $itemType;
    }
}

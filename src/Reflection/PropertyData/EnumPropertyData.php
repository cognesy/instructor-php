<?php
namespace Cognesy\Instructor\Reflection\PropertyData;

use Cognesy\Instructor\Schema\FCEnum;
use Cognesy\Instructor\Reflection\Enums\PhpType;
use Cognesy\Instructor\Reflection\TypeDefs\TypeDef;
use Cognesy\Instructor\Reflection\Utils\DescriptionUtils;
use Cognesy\Instructor\Reflection\Utils\ReflectionUtils;
use Exception;
use ReflectionEnum;
use ReflectionProperty;

class EnumPropertyData extends PropertyData {
    public PhpType $type = PhpType::ENUM;

    public array $values = [];

    protected function getPropertyData(ReflectionProperty $property) : void {
        parent::getPropertyData($property);
        $this->name = $property->getName();
        $this->description = DescriptionUtils::getPropertyDescription($property);
        $type = $property->getType();
        if (!$type) {
            throw new Exception('Property type is not defined');
        }
        $enum = new ReflectionEnum($type->getName());
        $this->values = ReflectionUtils::getEnumValues($enum);
    }

    public function toStruct() : FCEnum {
        $fcEnum = new FCEnum();
        $fcEnum->name = $this->name;
        $fcEnum->description = $this->description;
        $fcEnum->values = $this->values;
        return $fcEnum;
    }

    public static function asArrayItem(TypeDef $typeDef) : EnumPropertyData {
        $itemType = new EnumPropertyData();
        $itemType->name = 'items';
        $itemType->type = PhpType::ENUM;
        $itemType->values = $typeDef->values;
        return $itemType;
    }
}

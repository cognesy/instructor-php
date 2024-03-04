<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\PropertyData;

use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\PhpType;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Factories\TypeDefFactory;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\TypeDefs\TypeDef;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Utils\DescriptionUtils;
use ReflectionProperty;

abstract class PropertyData {
    public PhpType $type = PhpType::UNDEFINED;
    public TypeDef $typeDef;

    public string $name = '';
    public string $description = '';
    public bool $isNullable = false;
    public bool $isWritable = true;
    public mixed $defaultValue = null;

    public function __construct(?ReflectionProperty $property = null) {
        if ($property === null) {
            return;
        }
        $this->getPropertyData($property);
    }

    protected function getPropertyData(ReflectionProperty $property) : void {
        $this->name = $property->getName();
        $this->description = DescriptionUtils::getPropertyDescription($property);
        $this->typeDef = TypeDefFactory::fromReflectionProperty($property);
        $this->isWritable = !$property->isReadOnly() && $property->isPublic();
        $this->isNullable = $property->getType()?->allowsNull();
        if ($property->hasDefaultValue()) {
            $this->defaultValue = $property->getDefaultValue();
        }
    }

    abstract public function toStruct();
    abstract public static function asArrayItem(TypeDef $typeDef) : PropertyData;
}

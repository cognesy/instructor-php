<?php
namespace Cognesy\Instructor\Reflection\PropertyData;

use Cognesy\Instructor\Reflection\Enums\PhpType;
use Cognesy\Instructor\Reflection\Factories\TypeDefFactory;
use Cognesy\Instructor\Reflection\TypeDefs\TypeDef;
use Cognesy\Instructor\Reflection\Utils\DescriptionUtils;
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

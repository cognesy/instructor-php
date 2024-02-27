<?php
namespace Cognesy\Instructor\Reflection\ParameterData;

use Cognesy\Instructor\Reflection\Enums\PhpType;
use Cognesy\Instructor\Reflection\Factories\TypeDefFactory;
use Cognesy\Instructor\Reflection\TypeDefs\TypeDef;
use Cognesy\Instructor\Reflection\Utils\DescriptionUtils;
use ReflectionParameter;

abstract class ParameterData {
    public PhpType $type = PhpType::UNDEFINED;
    public TypeDef $typeDef;

    public string $name = '';
    public string $description = '';
    public mixed $defaultValue = null;
    public bool $nullable = true;

    public function __construct(?ReflectionParameter $property = null) {
        if ($property === null) {
            return;
        }
        $this->getParameterData($property);
    }

    protected function getParameterData(ReflectionParameter $parameter) : void {
        $this->name = $parameter->getName();
        $this->description = DescriptionUtils::getParameterDescription($parameter);
        $this->typeDef = TypeDefFactory::fromReflectionParameter($parameter);
        if ($parameter->isDefaultValueAvailable()) {
            $this->defaultValue = $parameter->getDefaultValue();
        }
        $this->nullable = $parameter->getType()?->allowsNull();
    }

    abstract public function toStruct();
    // abstract static public function asArrayItem(ReflectionParameter $parameter) : ParameterData;
    abstract static public function asArrayItem(TypeDef $typeDef) : ParameterData;
}

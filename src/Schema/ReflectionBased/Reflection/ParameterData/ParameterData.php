<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData;

use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\PhpType;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Factories\TypeDefFactory;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\TypeDefs\TypeDef;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Utils\DescriptionUtils;
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

<?php

namespace Cognesy\Instructor\Reflection;

use Cognesy\Instructor\Schema\FCFunction;
use Cognesy\Instructor\Reflection\Factories\ParameterDataFactory;
use Cognesy\Instructor\Reflection\ParameterData\ParameterData;
use ReflectionMethod;

class MethodData {
    public string $name;
    public string $description;
    /** @var ParameterData[] */
    public array $parameters;

    public function __construct(ReflectionMethod $method) {
        $this->getFunctionData($method);
    }

    public function getFunctionData(ReflectionMethod $method) : void {
        $this->name = $method->getName();
        $this->description = Utils\DescriptionUtils::getMethodDescription($method);
        $this->parameters = $this->getParameters($method);
    }

    /**
     * @return ParameterData[]
     */
    public function getParameters(ReflectionMethod $method) : array {
        $classProperties = $method->getParameters();
        $parameters = [];
        foreach ($classProperties as $property) {
            $parameters[] = ParameterDataFactory::make($property);
        }
        return $parameters;
    }

    public function toStruct(string $parentName = '', string $parentDescription = '') : FCFunction {
        $fcFunction = new FCFunction();
        $fcFunction->name = $parentName ?: $this->name;
        $fcFunction->description = $parentDescription ?: $this->description;
        foreach($this->parameters as $property) {
            $fcFunction->parameters[] = $property->toStruct();
            if (!$property->nullable) {
                $fcFunction->required[] = $property->name;
            }
        }
        return $fcFunction;
    }
}
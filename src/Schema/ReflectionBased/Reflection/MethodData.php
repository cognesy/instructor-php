<?php

namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection;

use Cognesy\Instructor\Schema\ReflectionBased\Data\FCFunction;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Factories\ParameterDataFactory;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData\ParameterData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Utils\DescriptionUtils;
use ReflectionMethod;

class MethodData {
    public string $name;
    public string $description;
    /** @var \Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData\ParameterData[] */
    public array $parameters;

    public function __construct(ReflectionMethod $method) {
        $this->getFunctionData($method);
    }

    public function getFunctionData(ReflectionMethod $method) : void {
        $this->name = $method->getName();
        $this->description = DescriptionUtils::getMethodDescription($method);
        $this->parameters = $this->getParameters($method);
    }

    /**
     * @return \Cognesy\Instructor\Schema\ReflectionBased\Reflection\ParameterData\ParameterData[]
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
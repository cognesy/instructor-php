<?php

namespace Cognesy\Schema\Utils;

use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Attributes\Instructions;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

class FunctionInfo
{
    private ReflectionFunction|ReflectionMethod $function;
    /** @var ReflectionParameter[] */
    private array $parameters;
    private $returnType;

    public function __construct(ReflectionFunction|ReflectionMethod $function) {
        $this->function = $function;
        $this->parameters = $function->getParameters();
    }

    static public function fromFunctionName(string $name) : self {
        return new self(new ReflectionFunction($name));
    }

    static public function fromMethodName(string $class, string $name) : self {
        return new self(new ReflectionMethod($class, $name));
    }

    public function getName() : string {
        return $this->function->getName();
    }

    public function getShortName() : string {
        return $this->function->getShortName();
    }

    public function hasParameter(string $name) : bool {
        return array_key_exists($name, $this->parameters);
    }

    public function isNullable(string $name) : bool {
        return $this->parameters[$name]->allowsNull();
    }

    public function isOptional(string $name) : bool {
        return $this->parameters[$name]->isOptional();
    }

    public function isVariadic(string $name) : bool {
        return $this->parameters[$name]->isVariadic();
    }

    public function hasDefaultValue(string $name) : bool {
        return $this->parameters[$name]->isDefaultValueAvailable();
    }

    public function getDefaultValue(string $name) : mixed {
        return $this->parameters[$name]->getDefaultValue();
    }

    public function getParameters() : array {
        return $this->parameters;
    }

    public function getDescription() : string {
        $descriptions = array_merge(
            AttributeUtils::getValues($this->function, Description::class, 'text'),
            AttributeUtils::getValues($this->function, Instructions::class, 'text'),
            [DocstringUtils::descriptionsOnly($this->function->getDocComment())],
        );
        return trim(implode('\n', array_filter($descriptions)));
    }

    public function getParameterDescription(string $argument) : string {
        if (!$this->hasParameter($argument)) {
            return '';
        }
        $methodDescription = $this->function->getDocComment();
        $descriptions = array_merge(
            AttributeUtils::getValues($this->parameters[$argument], Description::class, 'text'),
            AttributeUtils::getValues($this->parameters[$argument], Instructions::class, 'text'),
            [DocstringUtils::getParameterDescription($argument, $methodDescription)]
        );
        return trim(implode('\n', array_filter($descriptions)));
    }
}
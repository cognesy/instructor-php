<?php

namespace Cognesy\Schema\Utils;

use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Attributes\Instructions;
use ReflectionClass;
use ReflectionMethod;

class ConstructorInfo
{
    private ReflectionClass $reflectionClass;
    private ?ReflectionMethod $constructor;
    private string $class;
    /** @var ParameterInfo[] */
    private array $parameterInfos = [];
    private array $propertyMatchingCache = [];

    public function __construct(string|ReflectionClass $class)
    {
        $this->reflectionClass = is_string($class) ? new ReflectionClass($class) : $class;
        $this->class = $this->reflectionClass->getName();
        $this->constructor = $this->reflectionClass->getConstructor();
    }

    public static function fromClass(string $class): self
    {
        return new self($class);
    }

    public static function fromReflectionClass(ReflectionClass $reflectionClass): self
    {
        return new self($reflectionClass);
    }

    public function hasConstructor(): bool
    {
        return $this->constructor !== null;
    }

    public function getConstructor(): ?ReflectionMethod
    {
        return $this->constructor;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getParameterCount(): int
    {
        return $this->constructor?->getNumberOfParameters() ?? 0;
    }

    public function hasParameters(): bool
    {
        return $this->getParameterCount() > 0;
    }

    /** @return string[] */
    public function getParameterNames(): array
    {
        if (!$this->hasConstructor()) {
            return [];
        }
        return array_keys($this->getParameters());
    }

    /** @return ParameterInfo[] */
    public function getParameters(): array
    {
        if (!$this->hasConstructor()) {
            return [];
        }

        if (empty($this->parameterInfos)) {
            $this->parameterInfos = $this->makeParameterInfos();
        }
        return $this->parameterInfos;
    }

    public function getParameter(string $name): ParameterInfo
    {
        $parameters = $this->getParameters();
        if (!isset($parameters[$name])) {
            throw new \Exception("Parameter `$name` not found in constructor of class `$this->class`.");
        }
        return $parameters[$name];
    }

    public function hasParameter(string $name): bool
    {
        $parameters = $this->getParameters();
        return isset($parameters[$name]);
    }

    public function isNullable(string $name): bool
    {
        return $this->getParameter($name)->isNullable();
    }

    public function isOptional(string $name): bool
    {
        return $this->getParameter($name)->isOptional();
    }

    public function isVariadic(string $name): bool
    {
        return $this->getParameter($name)->isVariadic();
    }

    public function hasDefaultValue(string $name): bool
    {
        return $this->getParameter($name)->hasDefaultValue();
    }

    public function getDefaultValue(string $name): mixed
    {
        return $this->getParameter($name)->getDefaultValue();
    }

    public function getParameterDescription(string $name): string
    {
        return $this->getParameter($name)->getDescription();
    }

    public function getDescription(): string
    {
        if (!$this->hasConstructor()) {
            return '';
        }

        $descriptions = array_merge(
            AttributeUtils::getValues($this->constructor, Description::class, 'text'),
            AttributeUtils::getValues($this->constructor, Instructions::class, 'text'),
            [DocstringUtils::descriptionsOnly($this->constructor->getDocComment())],
        );
        return trim(implode('\n', array_filter($descriptions)));
    }

    /** @return string[] */
    public function getRequiredParameterNames(): array
    {
        $required = [];
        foreach ($this->getParameters() as $parameter) {
            if (!$parameter->isOptional()) {
                $required[] = $parameter->getName();
            }
        }
        return $required;
    }

    /** @return ParameterInfo[] */
    public function getRequiredParameters(): array
    {
        return array_filter($this->getParameters(), fn(ParameterInfo $param) => !$param->isOptional());
    }

    /** @return ParameterInfo[] */
    public function getOptionalParameters(): array
    {
        return array_filter($this->getParameters(), fn(ParameterInfo $param) => $param->isOptional());
    }

    // PROPERTY MATCHING METHODS /////////////////////////////////////////////////////////////////

    /** @return string[] */
    public function getPropertyMatchingParameterNames(): array
    {
        if (isset($this->propertyMatchingCache['names'])) {
            return $this->propertyMatchingCache['names'];
        }

        $matchingNames = [];
        foreach ($this->getParameterNames() as $parameterName) {
            if ($this->reflectionClass->hasProperty($parameterName)) {
                $matchingNames[] = $parameterName;
            }
        }

        return $this->propertyMatchingCache['names'] = $matchingNames;
    }

    /** @return ParameterInfo[] */
    public function getPropertyMatchingParameters(): array
    {
        if (isset($this->propertyMatchingCache['parameters'])) {
            return $this->propertyMatchingCache['parameters'];
        }

        $matching = [];
        foreach ($this->getParameters() as $parameter) {
            if ($this->reflectionClass->hasProperty($parameter->getName())) {
                $matching[$parameter->getName()] = $parameter;
            }
        }

        return $this->propertyMatchingCache['parameters'] = $matching;
    }

    public function parameterMatchesProperty(string $parameterName): bool
    {
        return in_array($parameterName, $this->getPropertyMatchingParameterNames(), true);
    }

    /** @return string[] */
    public function getNonPropertyMatchingParameterNames(): array
    {
        $allParameters = $this->getParameterNames();
        $matchingParameters = $this->getPropertyMatchingParameterNames();
        return array_diff($allParameters, $matchingParameters);
    }

    // FILTERING /////////////////////////////////////////////////////////////////

    /**
     * @param array<callable> $filters
     * @return array<string>
     */
    public function getFilteredParameterNames(array $filters): array
    {
        return array_keys($this->getFilteredParameterData(
            filters: $filters,
            extractor: fn(ParameterInfo $parameter) => $parameter->getName()
        ));
    }

    /**
     * @param array<callable> $filters
     * @return array<ParameterInfo>
     */
    public function getFilteredParameters(array $filters): array
    {
        return $this->filterParameters($filters);
    }

    // NORMALIZATION SUPPORT ////////////////////////////////////////////////////////////////

    /**
     * Prepare constructor arguments from data array, handling defaults and optional parameters
     */
    public function prepareConstructorArguments(array $data): array
    {
        if (!$this->hasConstructor()) {
            return [];
        }

        $args = [];
        foreach ($this->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $data)) {
                $args[] = $data[$name];
            } elseif ($param->hasDefaultValue()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->isOptional()) {
                $args[] = null;
            } else {
                throw new \Exception("Required parameter '$name' not provided for constructor");
            }
        }

        return $args;
    }

    /**
     * Check if the constructor can be called with the provided data
     */
    public function canBeCalledWith(array $data): bool
    {
        if (!$this->hasConstructor()) {
            return true;
        }

        foreach ($this->getRequiredParameters() as $param) {
            if (!array_key_exists($param->getName(), $data)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get constructor arguments that should be taken from data for property-like parameters
     */
    public function getPropertyConstructorArguments(array $data): array
    {
        $args = [];
        foreach ($this->getPropertyMatchingParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $data)) {
                $args[$name] = $data[$name];
            } elseif ($param->hasDefaultValue()) {
                $args[$name] = $param->getDefaultValue();
            } elseif ($param->isOptional() || $param->isNullable()) {
                $args[$name] = null;
            }
            // Skip required parameters that aren't in data - they'll cause constructor to fail
        }

        return $args;
    }

    // INTERNAL /////////////////////////////////////////////////////////////////

    /**
     * @param callable[] $filters
     * @return ParameterInfo[]
     */
    protected function filterParameters(array $filters): array
    {
        $parameterInfos = $this->getParameters();
        foreach ($filters as $filter) {
            if (!is_callable($filter)) {
                throw new \Exception("Filter must be a callable.");
            }
            $parameterInfos = array_filter($parameterInfos, $filter);
        }
        return $parameterInfos;
    }

    /** @return ParameterInfo[] */
    protected function makeParameterInfos(): array
    {
        if (!$this->hasConstructor()) {
            return [];
        }

        $parameters = $this->constructor->getParameters();
        $info = [];
        foreach ($parameters as $parameter) {
            $info[$parameter->getName()] = new ParameterInfo($parameter, $this->constructor);
        }
        return $info;
    }

    /**
     * @param array $filters
     * @param callable $extractor
     * @return array<string, mixed>
     */
    private function getFilteredParameterData(array $filters, callable $extractor): array
    {
        return array_map(
            callback: fn(ParameterInfo $parameter) => $extractor($parameter),
            array: $this->filterParameters($filters),
        );
    }
}
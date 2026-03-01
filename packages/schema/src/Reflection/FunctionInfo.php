<?php declare(strict_types=1);

namespace Cognesy\Schema\Reflection;

use Closure;
use Cognesy\Schema\Exceptions\ReflectionException;
use Cognesy\Schema\Utils\Descriptions;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

class FunctionInfo
{
    private ReflectionFunctionAbstract $function;

    /** @var array<string, ReflectionParameter> */
    private array $parameters;

    private function __construct(ReflectionFunctionAbstract $function) {
        $this->function = $function;
        $this->parameters = [];
        foreach ($function->getParameters() as $parameter) {
            $this->parameters[$parameter->getName()] = $parameter;
        }
    }

    /**
     * @param Closure(): mixed $closure
     */
    public static function fromClosure(Closure $closure) : self {
        $reflection = new ReflectionFunction($closure);
        $scopeClass = $reflection->getClosureScopeClass()?->getName();
        $functionName = $reflection->getName();

        if ($scopeClass !== null && !str_contains($functionName, '{closure')) {
            return new self(new ReflectionMethod($scopeClass, $functionName));
        }

        return new self($reflection);
    }

    public static function fromFunctionName(string $name) : self {
        return new self(new ReflectionFunction($name));
    }

    public static function fromMethodName(string $class, string $name) : self {
        return new self(new ReflectionMethod($class, $name));
    }

    public function getName() : string {
        return $this->function->getName();
    }

    public function getShortName() : string {
        return $this->function->getShortName();
    }

    public function hasParameter(string $name) : bool {
        return isset($this->parameters[$name]);
    }

    public function isNullable(string $name) : bool {
        return $this->parameter($name)->allowsNull();
    }

    public function isOptional(string $name) : bool {
        return $this->parameter($name)->isOptional();
    }

    public function isVariadic(string $name) : bool {
        return $this->parameter($name)->isVariadic();
    }

    public function isClassMethod() : bool {
        return $this->function instanceof ReflectionMethod;
    }

    public function hasDefaultValue(string $name) : bool {
        return $this->parameter($name)->isDefaultValueAvailable();
    }

    public function getDefaultValue(string $name) : mixed {
        return $this->parameter($name)->getDefaultValue();
    }

    /** @return array<string, ReflectionParameter> */
    public function getParameters() : array {
        return $this->parameters;
    }

    public function getDescription() : string {
        if ($this->function instanceof ReflectionMethod) {
            return Descriptions::forMethod(
                $this->function->getDeclaringClass()->getName(),
                $this->function->getName(),
            );
        }

        if (str_contains($this->function->getName(), '{closure')) {
            return '';
        }

        return Descriptions::forFunction($this->function->getName());
    }

    public function getParameterDescription(string $argument) : string {
        if ($this->function instanceof ReflectionMethod) {
            return Descriptions::forMethodParameter(
                $this->function->getDeclaringClass()->getName(),
                $this->function->getName(),
                $argument,
            );
        }

        if (str_contains($this->function->getName(), '{closure')) {
            return '';
        }

        return Descriptions::forFunctionParameter($this->function->getName(), $argument);
    }

    private function parameter(string $name) : ReflectionParameter {
        $parameter = $this->parameters[$name] ?? null;
        if ($parameter === null) {
            throw ReflectionException::parameterNotFound($name, $this->function->getName());
        }

        return $parameter;
    }
}

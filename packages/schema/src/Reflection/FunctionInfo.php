<?php declare(strict_types=1);

namespace Cognesy\Schema\Reflection;

use Closure;
use Cognesy\Schema\Utils\Descriptions;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

class FunctionInfo
{
    private ReflectionFunction|ReflectionMethod $function;
    /** @var ReflectionParameter[] */
    private array $parameters;
    private $returnType;

    private function __construct(ReflectionFunction|ReflectionMethod $function) {
        $this->function = $function;
        $this->parameters = $function->getParameters();
    }

    static public function fromClosure(Closure $closure) : self {
        $reflection = new \ReflectionFunction($closure);
        $class = $reflection->getClosureScopeClass()?->getName();
        $functionName = $reflection->getName();
        return new self(match(true) {
            !empty($class) => new ReflectionMethod($class, $functionName),
            !empty($functionName) => $reflection,
            default => throw new \InvalidArgumentException('Unsupported callable type: ' . gettype($callable)),
        });
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

    public function isClassMethod() : bool {
        return $this->function instanceof ReflectionMethod;
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
        return match(true) {
            $this->isClassMethod() => Descriptions::forMethod(
                $this->function->getDeclaringClass()->getName(),
                $this->function->getName()
            ),
            default => Descriptions::forFunction(
                $this->function->getName()
            ),
        };
    }

    public function getParameterDescription(string $argument) : string {
        return match(true) {
            $this->isClassMethod() => Descriptions::forMethodParameter(
                $this->function->getDeclaringClass()->getName(),
                $this->function->getName(),
                $argument
            ),
            default => Descriptions::forFunctionParameter(
                $this->function->getName(),
                $argument
            ),
        };
    }
}
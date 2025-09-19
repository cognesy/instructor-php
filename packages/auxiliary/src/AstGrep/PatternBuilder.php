<?php
declare(strict_types=1);

namespace Cognesy\Auxiliary\AstGrep;

class PatternBuilder
{
    private string $pattern = '';

    public static function create(): self {
        return new self();
    }

    public function classInstantiation(string $className = '$CLASS'): self {
        $this->pattern = sprintf('new %s($$$)', $className);
        return $this;
    }

    public function methodCall(string $methodName, string $object = '$OBJ'): self {
        $this->pattern = sprintf('%s->%s($$$)', $object, $methodName);
        return $this;
    }

    public function staticMethodCall(string $className, string $methodName): self {
        $this->pattern = sprintf('%s::%s($$$)', $className, $methodName);
        return $this;
    }

    public function functionCall(string $functionName): self {
        $this->pattern = sprintf('%s($$$)', $functionName);
        return $this;
    }

    public function classDefinition(string $className = '$CLASS'): self {
        $this->pattern = sprintf('class %s', $className);
        return $this;
    }

    public function classExtends(string $parentClass, string $className = '$CLASS'): self {
        $this->pattern = sprintf('class %s extends %s', $className, $parentClass);
        return $this;
    }

    public function classImplements(string $interface, string $className = '$CLASS'): self {
        $this->pattern = sprintf('class %s implements %s', $className, $interface);
        return $this;
    }

    public function traitUse(string $traitName): self {
        $this->pattern = sprintf('use %s;', $traitName);
        return $this;
    }

    public function propertyAccess(string $property, string $object = '$OBJ'): self {
        $this->pattern = sprintf('%s->%s', $object, $property);
        return $this;
    }

    public function staticPropertyAccess(string $className, string $property): self {
        $this->pattern = sprintf('%s::$%s', $className, $property);
        return $this;
    }

    public function assignment(string $variable, string $value = '$VALUE'): self {
        $this->pattern = sprintf('%s = %s', $variable, $value);
        return $this;
    }

    public function arrayAccess(string $array = '$ARRAY', string $key = '$KEY'): self {
        $this->pattern = sprintf('%s[%s]', $array, $key);
        return $this;
    }

    public function namespace(string $namespace = '$NAMESPACE'): self {
        $this->pattern = sprintf('namespace %s', $namespace);
        return $this;
    }

    public function useStatement(string $class): self {
        $this->pattern = sprintf('use %s', $class);
        return $this;
    }

    public function returnStatement(string $value = '$VALUE'): self {
        $this->pattern = sprintf('return %s', $value);
        return $this;
    }

    public function throwStatement(string $exception = '$EXCEPTION'): self {
        $this->pattern = sprintf('throw %s', $exception);
        return $this;
    }

    public function ifStatement(string $condition = '$CONDITION'): self {
        $this->pattern = sprintf('if (%s)', $condition);
        return $this;
    }

    public function foreachLoop(string $array = '$ARRAY', string $value = '$VALUE'): self {
        $this->pattern = sprintf('foreach (%s as %s)', $array, $value);
        return $this;
    }

    public function tryCatch(string $exception = 'Exception', string $variable = '$e'): self {
        $this->pattern = sprintf('try { $$$ } catch (%s %s) { $$$ }', $exception, $variable);
        return $this;
    }

    public function custom(string $pattern): self {
        $this->pattern = $pattern;
        return $this;
    }

    public function build(): string {
        return $this->pattern;
    }

    public function __toString(): string {
        return $this->build();
    }
}
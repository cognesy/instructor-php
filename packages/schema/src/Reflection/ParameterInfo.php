<?php

namespace Cognesy\Schema\Reflection;

use Cognesy\Schema\Utils\AttributeUtils;
use Cognesy\Schema\Utils\Descriptions;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionType;

class ParameterInfo
{
    private ReflectionParameter $reflection;
    private ReflectionFunction|ReflectionMethod $function;
    private string $name;
    private bool $belongsToClassMethod = false;

    public function __construct(
        ReflectionParameter $paramReflection,
        ReflectionFunction|ReflectionMethod $functionReflection,
    ) {
        $this->reflection = $paramReflection;
        $this->function = $functionReflection;
        if ($functionReflection instanceof ReflectionMethod) {
            $this->belongsToClassMethod = true;
        }
        $this->name = $paramReflection->getName();
    }

    public static function fromName(
        ReflectionFunction|ReflectionMethod $function,
        string $parameterName,
    ) : self {
        $parameters = $function->getParameters();
        foreach ($parameters as $parameter) {
            if ($parameter->getName() === $parameterName) {
                return new self($parameter, $function);
            }
        }
        throw new \Exception("Parameter `$parameterName` not found in function/method.");
    }

    public function getName(): string {
        return $this->name;
    }

    public function getPosition(): int {
        return $this->reflection->getPosition();
    }

    public function isNullable(): bool {
        return $this->reflection->allowsNull();
    }

    public function isOptional(): bool {
        return $this->reflection->isOptional();
    }

    public function isVariadic(): bool {
        return $this->reflection->isVariadic();
    }

    public function isPassedByReference(): bool {
        return $this->reflection->isPassedByReference();
    }

    public function hasDefaultValue(): bool {
        return $this->reflection->isDefaultValueAvailable();
    }

    public function isClassMethodParameter(): bool {
        return $this->belongsToClassMethod;
    }

    public function getDefaultValue(): mixed {
        if (!$this->hasDefaultValue()) {
            throw new \Exception("Parameter `{$this->name}` has no default value.");
        }
        return $this->reflection->getDefaultValue();
    }

    public function isDefaultValueConstant(): bool {
        return $this->reflection->isDefaultValueConstant();
    }

    public function getDefaultValueConstantName(): ?string {
        return $this->reflection->isDefaultValueConstant()
            ? $this->reflection->getDefaultValueConstantName()
            : null;
    }

    public function hasType(): bool {
        return $this->reflection->hasType();
    }

    public function getTypeName(): string {
        if (!$this->hasType()) {
            return 'mixed';
        }

        $type = $this->reflection->getType();

        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof \ReflectionUnionType) {
            $types = array_map(fn(ReflectionType $t) => $t->getName(), $type->getTypes());
            return implode('|', $types);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            $types = array_map(fn(ReflectionType $t) => $t->getName(), $type->getTypes());
            return implode('&', $types);
        }

        return 'mixed';
    }

    public function getDescription(): string {
        return match(true) {
            $this->belongsToClassMethod => Descriptions::forMethodParameter(
                class: $this->function->getDeclaringClass()->getName(),
                methodName: $this->function->getShortName(),
                parameterName: $this->name
            ),
            default => Descriptions::forFunctionParameter(
                functionName: $this->function->getName(),
                parameterName: $this->name
            ),
        };
    }

    public function hasAttribute(string $attributeClass): bool {
        return AttributeUtils::hasAttribute($this->reflection, $attributeClass);
    }

    /** @return array<string|bool|int|float> */
    public function getAttributeValues(string $attributeClass, string $attributeProperty): array {
        return AttributeUtils::getValues($this->reflection, $attributeClass, $attributeProperty);
    }

    public function isClassType(): bool {
        if (!$this->hasType()) {
            return false;
        }

        $type = $this->reflection->getType();
        return $type instanceof \ReflectionNamedType && !$type->isBuiltin();
    }

    public function getClassName(): ?string {
        if (!$this->isClassType()) {
            return null;
        }

        $type = $this->reflection->getType();
        return $type instanceof \ReflectionNamedType ? $type->getName() : null;
    }

    public function isArray(): bool {
        return $this->getTypeName() === 'array';
    }

    public function allowsNull(): bool {
        return $this->isNullable();
    }

    public function getReflection(): ReflectionParameter {
        return $this->reflection;
    }
}
<?php

namespace Cognesy\Schema\Utils;

use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Attributes\Instructions;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionType;

class ParameterInfo
{
    private ReflectionParameter $reflection;
    private ReflectionFunction|ReflectionMethod $function;
    private string $name;

    public function __construct(
        ReflectionParameter $paramReflection,
        ReflectionFunction|ReflectionMethod $functionReflection,
    ) {
        $this->reflection = $paramReflection;
        $this->function = $functionReflection;
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
        // get #[Description] attributes
        $descriptions = array_merge(
            AttributeUtils::getValues($this->reflection, Description::class, 'text'),
            AttributeUtils::getValues($this->reflection, Instructions::class, 'text'),
        );

        // get parameter description from PHPDoc
        $methodDescription = $this->function->getDocComment();
        $docDescription = DocstringUtils::getParameterDescription($this->name, $methodDescription);
        if ($docDescription) {
            $descriptions[] = $docDescription;
        }

        return trim(implode('\n', array_filter($descriptions)));
    }

    public function hasAttribute(string $attributeClass): bool {
        return AttributeUtils::hasAttribute($this->reflection, $attributeClass);
    }

    /** @return array<string|bool|int|float> */
    public function getAttributeValues(string $attributeClass, string $attributeProperty): array {
        return AttributeUtils::getValues($this->reflection, $attributeClass, $attributeProperty);
    }

    public function isBuiltinType(): bool {
        if (!$this->hasType()) {
            return false;
        }

        $type = $this->reflection->getType();
        return $type instanceof \ReflectionNamedType && $type->isBuiltin();
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

    public function canBePassedValue(mixed $value): bool {
        // If parameter allows null and value is null
        if ($value === null) {
            return $this->allowsNull();
        }

        // If no type constraint, accept any non-null value
        if (!$this->hasType()) {
            return true;
        }

        $typeName = $this->getTypeName();

        // Handle built-in types
        return match ($typeName) {
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'callable' => is_callable($value),
            'mixed' => true,
            default => $this->isClassType() ? is_object($value) && is_a($value, $typeName) : true,
        };
    }
}
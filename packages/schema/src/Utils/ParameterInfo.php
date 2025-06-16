<?php

namespace Cognesy\Schema\Utils;

use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Attributes\Instructions;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;

class ParameterInfo
{
    private ReflectionParameter $reflection;
    private ReflectionFunction|ReflectionMethod $function;
    private string $name;
    private PropertyInfoExtractor $extractor;
    private array $types;

    public function __construct(
        ReflectionParameter                 $paramReflection,
        ReflectionFunction|ReflectionMethod $functionReflection,
    ) {
        $this->reflection = $paramReflection;
        $this->function = $functionReflection;
        $this->name = $paramReflection->getName();
    }

    public static function fromName(ReflectionFunction|ReflectionMethod $function, string $parameterName): self
    {
        $parameters = $function->getParameters();
        foreach ($parameters as $parameter) {
            if ($parameter->getName() === $parameterName) {
                return new self($parameter, $function);
            }
        }
        throw new \Exception("Parameter `$parameterName` not found in function/method.");
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPosition(): int
    {
        return $this->reflection->getPosition();
    }

    public function isNullable(): bool
    {
        return $this->reflection->allowsNull();
    }

    public function isOptional(): bool
    {
        return $this->reflection->isOptional();
    }

    public function isVariadic(): bool
    {
        return $this->reflection->isVariadic();
    }

    public function isPassedByReference(): bool
    {
        return $this->reflection->isPassedByReference();
    }

    public function hasDefaultValue(): bool
    {
        return $this->reflection->isDefaultValueAvailable();
    }

    public function getDefaultValue(): mixed
    {
        if (!$this->hasDefaultValue()) {
            throw new \Exception("Parameter `{$this->name}` has no default value.");
        }
        return $this->reflection->getDefaultValue();
    }

    public function isDefaultValueConstant(): bool
    {
        return $this->reflection->isDefaultValueConstant();
    }

    public function getDefaultValueConstantName(): ?string
    {
        return $this->reflection->isDefaultValueConstant()
            ? $this->reflection->getDefaultValueConstantName()
            : null;
    }

    public function hasType(): bool
    {
        return $this->reflection->hasType();
    }

    public function getTypeName(): string
    {
        if (!$this->hasType()) {
            return 'mixed';
        }

        $type = $this->reflection->getType();

        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof \ReflectionUnionType) {
            $types = array_map(fn($t) => $t->getName(), $type->getTypes());
            return implode('|', $types);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            $types = array_map(fn($t) => $t->getName(), $type->getTypes());
            return implode('&', $types);
        }

        return 'mixed';
    }

    public function getTypes(): array
    {
        if (!isset($this->types)) {
            $this->types = $this->makeTypes();
        }
        return $this->types;
    }

    public function getType(): Type
    {
        $parameterTypes = $this->getTypes();
        if (!count($parameterTypes)) {
            throw new \Exception("No type found for parameter: {$this->name}");
        }
        if (count($parameterTypes) > 1) {
            throw new \Exception("Unsupported union type found for parameter: {$this->name}");
        }
        return $parameterTypes[0];
    }

    public function getBuiltinTypeName(): string
    {
        try {
            $type = $this->getType();
            $builtInType = $type->getBuiltinType();
            return match($builtInType) {
                Type::BUILTIN_TYPE_INT => 'int',
                Type::BUILTIN_TYPE_FLOAT => 'float',
                Type::BUILTIN_TYPE_STRING => 'string',
                Type::BUILTIN_TYPE_BOOL => 'bool',
                Type::BUILTIN_TYPE_ARRAY => $this->getCollectionOrArrayType($type),
                Type::BUILTIN_TYPE_OBJECT => $type->getClassName() ?? 'object',
                Type::BUILTIN_TYPE_CALLABLE => 'callable',
                Type::BUILTIN_TYPE_ITERABLE => 'iterable',
                Type::BUILTIN_TYPE_RESOURCE => 'resource',
                Type::BUILTIN_TYPE_NULL => 'null',
                default => 'mixed',
            };
        } catch (\Exception $e) {
            return $this->getTypeName();
        }
    }

    public function getDescription(): string
    {
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

    public function hasAttribute(string $attributeClass): bool
    {
        return AttributeUtils::hasAttribute($this->reflection, $attributeClass);
    }

    /** @return array<string|bool|int|float> */
    public function getAttributeValues(string $attributeClass, string $attributeProperty): array
    {
        return AttributeUtils::getValues($this->reflection, $attributeClass, $attributeProperty);
    }

    public function isBuiltinType(): bool
    {
        if (!$this->hasType()) {
            return false;
        }

        $type = $this->reflection->getType();
        return $type instanceof \ReflectionNamedType && $type->isBuiltin();
    }

    public function isClassType(): bool
    {
        if (!$this->hasType()) {
            return false;
        }

        $type = $this->reflection->getType();
        return $type instanceof \ReflectionNamedType && !$type->isBuiltin();
    }

    public function getClassName(): ?string
    {
        if (!$this->isClassType()) {
            return null;
        }

        $type = $this->reflection->getType();
        return $type instanceof \ReflectionNamedType ? $type->getName() : null;
    }

    public function isArray(): bool
    {
        return $this->getTypeName() === 'array';
    }

    public function allowsNull(): bool
    {
        return $this->isNullable();
    }

    public function canBePassedValue(mixed $value): bool
    {
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
        return match($typeName) {
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

    // INTERNAL /////////////////////////////////////////////////////////////////////////

    private function getCollectionOrArrayType(Type $type): string
    {
        $valueType = $type->getCollectionValueTypes();
        $valueType = $valueType[0] ?? null;
        if (is_null($valueType)) {
            return 'array';
        }

        $builtInType = $valueType->getBuiltinType();
        return match($builtInType) {
            Type::BUILTIN_TYPE_INT => 'int[]',
            Type::BUILTIN_TYPE_FLOAT => 'float[]',
            Type::BUILTIN_TYPE_STRING => 'string[]',
            Type::BUILTIN_TYPE_BOOL => 'bool[]',
            Type::BUILTIN_TYPE_ARRAY => throw new \Exception("Nested arrays are not supported"),
            Type::BUILTIN_TYPE_OBJECT => ($valueType->getClassName() ?? 'object') . '[]',
            Type::BUILTIN_TYPE_CALLABLE => 'callable[]',
            Type::BUILTIN_TYPE_ITERABLE => 'iterable[]',
            Type::BUILTIN_TYPE_RESOURCE => 'resource[]',
            Type::BUILTIN_TYPE_NULL => 'null[]',
            default => 'array',
        };
    }

    private function extractor(): PropertyInfoExtractor
    {
        if (!isset($this->extractor)) {
            $this->extractor = $this->makeExtractor();
        }
        return $this->extractor;
    }

    protected function makeExtractor(): PropertyInfoExtractor
    {
        // initialize extractor instance
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        return new PropertyInfoExtractor(
            [$reflectionExtractor],
            [new PhpStanExtractor(), $phpDocExtractor, $reflectionExtractor],
            [$phpDocExtractor],
            [$reflectionExtractor],
            [$reflectionExtractor]
        );
    }

    protected function makeTypes(): array
    {
        // For parameters, we need to extract type info differently
        // since PropertyInfoExtractor is designed for class properties
        if (!$this->hasType()) {
            return [new Type(Type::BUILTIN_TYPE_STRING)];
        }

        $reflectionType = $this->reflection->getType();

        if ($reflectionType instanceof \ReflectionNamedType) {
            return [$this->convertReflectionTypeToPropertyInfoType($reflectionType)];
        }

        if ($reflectionType instanceof \ReflectionUnionType) {
            $types = [];
            foreach ($reflectionType->getTypes() as $type) {
                $types[] = $this->convertReflectionTypeToPropertyInfoType($type);
            }
            return $types;
        }

        // Default fallback
        return [new Type(Type::BUILTIN_TYPE_STRING)];
    }

    private function convertReflectionTypeToPropertyInfoType(\ReflectionNamedType $reflectionType): Type
    {
        $typeName = $reflectionType->getName();
        $isNullable = $reflectionType->allowsNull();

        if ($reflectionType->isBuiltin()) {
            $builtinType = match($typeName) {
                'int' => Type::BUILTIN_TYPE_INT,
                'float' => Type::BUILTIN_TYPE_FLOAT,
                'string' => Type::BUILTIN_TYPE_STRING,
                'bool' => Type::BUILTIN_TYPE_BOOL,
                'array' => Type::BUILTIN_TYPE_ARRAY,
                'object' => Type::BUILTIN_TYPE_OBJECT,
                'callable' => Type::BUILTIN_TYPE_CALLABLE,
                'iterable' => Type::BUILTIN_TYPE_ITERABLE,
                'resource' => Type::BUILTIN_TYPE_RESOURCE,
                'null' => Type::BUILTIN_TYPE_NULL,
                'mixed' => Type::BUILTIN_TYPE_STRING, // fallback
                default => Type::BUILTIN_TYPE_STRING,
            };

            return new Type($builtinType, $isNullable);
        }

        return new Type(Type::BUILTIN_TYPE_OBJECT, $isNullable, $typeName);
    }
}
<?php

namespace Cognesy\Schema\Utils;

use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Attributes\Instructions;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;

class PropertyInfo
{
    private ReflectionProperty $reflection;
    private string $class;
    private string $propertyName;
    private PropertyInfoExtractor $extractor;
    private array $types;
    private ReflectionClass $parentClass;
    private ClassInfo $classInfo;
    private ?bool $hasAccessorCandidates = null;
    private ?bool $hasMutatorCandidates = null;
    private ?array $constructorParams;

    static public function fromName(string $class, string $property): PropertyInfo {
        $reflection = new ReflectionProperty($class, $property);
        return new PropertyInfo($reflection);
    }

    public function __construct(ReflectionProperty $reflection) {
        $this->reflection = $reflection;
        $this->propertyName = $reflection->getName();
        $this->parentClass = $reflection->getDeclaringClass();
        $this->class = $this->parentClass->getName();
        $this->classInfo = new ClassInfo($this->class);
        $this->constructorParams = $this->classInfo->getConstructorInfo()->getParameterNames();
    }

    public function getName(): string {
        return $this->propertyName;
    }

    public function getTypes(): array {
        if (!isset($this->types)) {
            $this->types = $this->makeTypes();
        }
        return $this->types;
    }

    public function getType(): Type {
        $propertyTypes = $this->getTypes();
        if (!count($propertyTypes)) {
            throw new \Exception("No type found for property: $this->class::$this->propertyName");
        }
        if (count($propertyTypes) > 1) {
            throw new \Exception("Unsupported union type found for property: $this->class::$this->propertyName");
        }
        return $propertyTypes[0];
    }

    public function getTypeName(): string {
        $type = $this->getType();
        $builtInType = $type->getBuiltinType();
        return match ($builtInType) {
            Type::BUILTIN_TYPE_INT => 'int',
            Type::BUILTIN_TYPE_FLOAT => 'float',
            Type::BUILTIN_TYPE_STRING => 'string',
            Type::BUILTIN_TYPE_BOOL => 'bool',
            Type::BUILTIN_TYPE_ARRAY => $this->getCollectionOrArrayType($type),
            Type::BUILTIN_TYPE_OBJECT => $this->getType()->getClassName(),
            Type::BUILTIN_TYPE_CALLABLE => 'callable',
            Type::BUILTIN_TYPE_ITERABLE => 'iterable',
            Type::BUILTIN_TYPE_RESOURCE => 'resource',
            Type::BUILTIN_TYPE_NULL => 'null',
            default => 'mixed',
        };
    }

    public function getDescription(): string {
        // get #[Description] attributes
        $descriptions = array_merge(
            AttributeUtils::getValues($this->reflection, Description::class, 'text'), AttributeUtils::getValues($this->reflection, Instructions::class, 'text'),
//            AttributeUtils::getValues($this->reflection, InputField::class, 'description'),
//            AttributeUtils::getValues($this->reflection, OutputField::class, 'description'),
        );
        // get property description from PHPDoc
        $descriptions[] = $this->extractor()->getShortDescription($this->class, $this->propertyName);
        $descriptions[] = $this->extractor()->getLongDescription($this->class, $this->propertyName);

        return trim(implode('\n', array_filter($descriptions)));
    }

    public function hasAttribute(string $attributeClass): bool {
        return AttributeUtils::hasAttribute($this->reflection, $attributeClass);
    }

    /** @return array<string|bool|int|float> */
    public function getAttributeValues(string $attributeClass, string $attributeProperty): array {
        return AttributeUtils::getValues($this->reflection, $attributeClass, $attributeProperty);
    }

    public function isPublic(): bool {
        return $this->reflection->isPublic();
    }

    public function isNullable(): bool {
        $types = $this->getTypes();
        if (is_null($types)) {
            return false;
        }
        foreach ($types as $type) {
            if ($type->isNullable()) {
                return true;
            }
        }
        return false;
    }

    public function isReadOnly(): bool {
        return $this->reflection->isReadOnly();
    }

    public function isStatic(): bool {
        return $this->reflection->isStatic();
    }

    public function isDeserializable() : bool {
        return $this->reflection->isPublic()
            || $this->matchesConstructorParam()
            || $this->hasMutatorCandidates($this->propertyName);
    }

    public function isRequired() : bool {
        // case 1: property is PUBLIC or NOT, but has a matching constructor parameter
        if ($this->matchesConstructorParam()) {
            $constructorParam = $this->getConstructorParam($this->propertyName);
            return match(true) {
                // if constructor parameter has a default value, it is not required
                $constructorParam->isNullable() => false,
                $constructorParam->hasDefaultValue() => false,
                //$constructorParam->isOptional() => false,
                default => true,
            };
        }

        // case 2: property is public and is not nullable
        if ($this->isPublic()) {
            return match(true) {
                $this->isNullable() => false,
                //$this->hasDefaultValue() => false, // exclude this - default value should not make it optional
                default => true,
            };
        }

        // case 3: property is NOT public, but has a mutator method and the mutator parameter is not nullable
        if (!$this->isPublic() && $this->hasMutatorCandidates($this->propertyName)) {
            $mutatorParam = $this->getMutatorParam($this->propertyName);
            if (is_null($mutatorParam)) {
                return false; // no mutator found
            }
            return match(true) {
                $this->isNullable() => false, // if property is nullable, it is not required
                $mutatorParam->isNullable() => false, // if mutator parameter is nullable, it is not required
                $mutatorParam->hasDefaultValue() => false, // exclude this - default value should not make it optional
                //$mutatorParam->isOptional() => false,
                default => true,
            };
        }

        return false;
    }

    public function constructorParams() : array {
        return $this->constructorParams;
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////////

    private function matchesConstructorParam() : bool {
        return in_array($this->propertyName, $this->constructorParams(), true);
    }

    private function getCollectionOrArrayType(Type $type): string {
        $valueType = $type->getCollectionValueTypes();
        $valueType = $valueType[0] ?? null;
        if (is_null($valueType)) {
            return 'array';
        }
        $builtInType = $valueType->getBuiltinType();
        return match ($builtInType) {
            Type::BUILTIN_TYPE_INT => 'int',
            Type::BUILTIN_TYPE_FLOAT => 'float',
            Type::BUILTIN_TYPE_STRING => 'string',
            Type::BUILTIN_TYPE_BOOL => 'bool',
            Type::BUILTIN_TYPE_ARRAY => throw new \Exception("Nested arrays are not supported"),
            Type::BUILTIN_TYPE_OBJECT => $this->getType()->getClassName(),
            Type::BUILTIN_TYPE_CALLABLE => 'callable',
            Type::BUILTIN_TYPE_ITERABLE => 'iterable',
            Type::BUILTIN_TYPE_RESOURCE => 'resource',
            Type::BUILTIN_TYPE_NULL => 'null',
            default => 'array',
        };
    }

    private function extractor(): PropertyInfoExtractor {
        if (!isset($this->extractor)) {
            $this->extractor = $this->makeExtractor();
        }
        return $this->extractor;
    }

    protected function makeExtractor(): PropertyInfoExtractor {
        // initialize extractor instance
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        return new PropertyInfoExtractor(
            [$reflectionExtractor], [new PhpStanExtractor(), $phpDocExtractor, $reflectionExtractor], [$phpDocExtractor], [$reflectionExtractor], [$reflectionExtractor]
        );
    }

    protected function makeTypes(): array {
        $types = $this->extractor()->getTypes($this->class, $this->propertyName);
        if (is_null($types)) {
            $types = [new Type(Type::BUILTIN_TYPE_STRING)];
        }
        return $types;
    }

    public function getClass(): string {
        return $this->class;
    }

    private function hasMutatorCandidates(string $propertyName) : bool {
        if (!is_null($this->hasMutatorCandidates)) {
            return $this->hasMutatorCandidates;
        }

        $patterns = [
            fn($name) => 'set' . ucfirst($name),
            //fn($name) => 'with' . ucfirst($name),
        ];

        foreach ($patterns as $pattern) {
            $methodName = $pattern($propertyName);
            if (!$this->parentClass->hasMethod($methodName)) {
                continue; // Skip if method does not exist
            }
            $method = $this->parentClass->getMethod($methodName);
            // Check if the method is public
            if (!$method->isPublic()) {
                continue; // Skip non-public methods
            }
            // Check if the method is a setter (one parameter)
            if ($method->getNumberOfParameters() !== 1) {
                continue; // Skip methods other than with one parameter
            }
            // Check if the method returns a value
            if ('void' !== $method->getReturnType()?->getName()) {
                continue; // Skip methods that do not return void
            }
            return true;
        }
        return $this->hasMutatorCandidates = false;
    }

    private function hasAccessorCandidates(string $propertyName) : bool {
        if (!is_null($this->hasAccessorCandidates)) {
            return $this->hasAccessorCandidates;
        }

        $patterns = [
            //fn($name) => $name,
            fn($name) => 'has'. ucfirst($name),
            fn($name) => 'get' . ucfirst($name),
            fn($name) => 'is' . ucfirst($name),
        ];

        foreach ($patterns as $pattern) {
            $methodName = $pattern($propertyName);
            if (!$this->parentClass->hasMethod($methodName)) {
                continue;
            }
            $method = $this->parentClass->getMethod($methodName);
            // Check if the method is public
            if (!$method->isPublic()) {
                continue;
            }
            // Check if the method is a getter (no parameters)
            if ($method->getNumberOfParameters() > 0) {
                continue;
            }
            // Check if the method returns a value
            if ('void' === $method->getReturnType()?->getName()) {
                continue;
            }
            return true;
        }
        return $this->hasAccessorCandidates = false;
    }

    private function getMutatorParam(string $name) : ?ParameterInfo {
        $mutatorMethodName = 'set' . ucfirst($name);
        if (!$this->parentClass->hasMethod($mutatorMethodName)) {
            return null;
        }
        $mutatorMethod = $this->parentClass->getMethod($mutatorMethodName);
        $parameter = $mutatorMethod->getParameters()[0] ?? null;
        if (is_null($parameter)) {
            return null;
        }
        return new ParameterInfo(
            paramReflection: $parameter,
            functionReflection: $mutatorMethod,
        );
    }

    private function getConstructorParam(string $propertyName) : ParameterInfo {
        return $this->classInfo->getConstructorInfo()->getParameter($propertyName);
    }
}
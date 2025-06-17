<?php

namespace Cognesy\Schema\Utils;

use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Attributes\InputField;
use Cognesy\Schema\Attributes\Instructions;
use Cognesy\Schema\Attributes\OutputField;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

class PropertyInfo
{
    private ReflectionProperty $reflection;
    private string $class;
    private string $propertyName;
    private PropertyInfoExtractor $extractor;
    private ?Type $type = null;
    private ReflectionClass $parentClass;
    private ClassInfo $classInfo;

    // cached values
    private ?bool $hasAccessorCandidates = null;
    private ?bool $hasMutatorCandidates = null;
    private ?array $constructorParams = null;

    static public function fromName(string $class, string $property) : PropertyInfo {
        $reflection = new ReflectionProperty($class, $property);
        return new PropertyInfo($reflection);
    }

    public function __construct(
        ReflectionProperty $reflection,
    ) {
        $this->reflection = $reflection;
        $this->propertyName = $reflection->getName();
        $this->parentClass = $reflection->getDeclaringClass();
        $this->class = $this->parentClass->getName();
        $this->classInfo = new ClassInfo($this->class);
    }

    public function getName() : string {
        return $this->propertyName;
    }

    public function getType(): Type {
        if (!isset($this->type)) {
            $this->type = $this->makeTypes();
        }
        return $this->type;
    }

    public function getTypeName() : string {
        $type = $this->getType();
        return match(true) {
            $type->isIdentifiedBy(TypeIdentifier::INT) => 'int',
            $type->isIdentifiedBy(TypeIdentifier::FLOAT) => 'float',
            $type->isIdentifiedBy(TypeIdentifier::STRING) => 'string',
            $type->isIdentifiedBy(TypeIdentifier::BOOL) => 'bool',
            $type->isIdentifiedBy(TypeIdentifier::ARRAY) => $this->getCollectionOrArrayType($type),
            $type->isIdentifiedBy(TypeIdentifier::OBJECT) => $type->getClassName(),
            $type->isIdentifiedBy(TypeIdentifier::CALLABLE) => 'callable',
            $type->isIdentifiedBy(TypeIdentifier::ITERABLE) => 'iterable',
            $type->isIdentifiedBy(TypeIdentifier::RESOURCE) => 'resource',
            $type->isIdentifiedBy(TypeIdentifier::NULL) => 'null',
            default => 'mixed',
        };
    }

    public function getDescription(): string {
        // get #[Description] attributes
        $descriptions = array_merge(
            AttributeUtils::getValues($this->reflection, Description::class, 'text'),
            AttributeUtils::getValues($this->reflection, Instructions::class, 'text'),
            AttributeUtils::getValues($this->reflection, InputField::class, 'description'),
            AttributeUtils::getValues($this->reflection, OutputField::class, 'description'),
        );
        // get property description from PHPDoc
        $descriptions[] = $this->extractor()->getShortDescription($this->class, $this->propertyName);
        $descriptions[] = $this->extractor()->getLongDescription($this->class, $this->propertyName);

        return trim(implode('\n', array_filter($descriptions)));
    }

    public function hasAttribute(string $attributeClass) : bool {
        return AttributeUtils::hasAttribute($this->reflection, $attributeClass);
    }

    /** @return array<string|bool|int|float> */
    public function getAttributeValues(string $attributeClass, string $attributeProperty) : array {
        return AttributeUtils::getValues($this->reflection, $attributeClass, $attributeProperty);
    }

    public function isPublic() : bool {
        return $this->reflection->isPublic();
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

    public function isNullable() : bool {
        return $this->getType()->isNullable();
    }

    public function isReadOnly() : bool {
        return $this->reflection->isReadOnly();
    }

    public function isStatic() : bool {
        return $this->reflection->isStatic();
    }

    public function getClass() : string {
        return $this->class;
    }

    public function hasDefaultValue() : bool {
        return $this->reflection->hasDefaultValue();
    }


    // INTERNAL /////////////////////////////////////////////////////////////////////////

    private function matchesConstructorParam() : bool {
        return in_array($this->propertyName, $this->constructorParams(), true);
    }

    private function constructorParams() : array {
        if (!isset($this->constructorParams)) {
            $this->constructorParams = $this->classInfo->getConstructorInfo()->getParameterNames();
        }
        return $this->constructorParams;
    }

    private function getCollectionOrArrayType(Type $type) : string {
        $valueType = $type->getCollectionValueType();
        if (is_null($valueType)) {
            return 'array';
        }
        return match(true) {
            $valueType->isIdentifiedBy(TypeIdentifier::INT) => 'int',
            $valueType->isIdentifiedBy(TypeIdentifier::FLOAT) => 'float',
            $valueType->isIdentifiedBy(TypeIdentifier::STRING) => 'string',
            $valueType->isIdentifiedBy(TypeIdentifier::BOOL) => 'bool',
            $valueType->isIdentifiedBy(TypeIdentifier::ARRAY) => throw new \Exception("Nested arrays are not supported"),
            $valueType->isIdentifiedBy(TypeIdentifier::OBJECT) => $valueType->getClassName(),
            $valueType->isIdentifiedBy(TypeIdentifier::CALLABLE) => 'callable',
            $valueType->isIdentifiedBy(TypeIdentifier::ITERABLE) => 'iterable',
            $valueType->isIdentifiedBy(TypeIdentifier::RESOURCE) => 'resource',
            $valueType->isIdentifiedBy(TypeIdentifier::NULL) => 'null',
            default => 'array',
        };
    }

    private function extractor() : PropertyInfoExtractor {
        if (!isset($this->extractor)) {
            $this->extractor = $this->makeExtractor();
        }
        return $this->extractor;
    }

    protected function makeExtractor() : PropertyInfoExtractor {
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

    protected function makeTypes() : Type {
        $type = $this->extractor()->getType($this->class, $this->propertyName);
        if (is_null($type)) {
            $type = Type::mixed();
        }
        return $type;
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
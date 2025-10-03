<?php declare(strict_types=1);

namespace Cognesy\Schema\Reflection;

use Cognesy\Schema\Contracts\CanGetPropertyType;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Utils\AttributeUtils;
use Cognesy\Schema\Utils\Compat\PropertyInfoV6Adapter;
use Cognesy\Schema\Utils\Compat\PropertyInfoV7Adapter;
use Cognesy\Schema\Utils\Descriptions;
use ReflectionClass;
use ReflectionProperty;

class PropertyInfo
{
    private ReflectionProperty $reflection;
    /** @var class-string */
    private string $class;
    private string $propertyName;
    private ReflectionClass $parentClass;
    private ClassInfo $classInfo;
    private CanGetPropertyType $typeInfoAdapter;

    // cached values
    private ?bool $hasMutatorCandidates = null;
    private ?bool $hasAccessorCandidates = null;
    private ?array $constructorParams = null;

    static public function fromName(
        string $class,
        string $property
    ) : PropertyInfo {
        $reflection = new ReflectionProperty($class, $property);
        return PropertyInfo::fromReflection($reflection);
    }

    static public function fromReflection(
        ReflectionProperty $reflection
    ) : PropertyInfo {
        return new PropertyInfo($reflection);
    }

    private function __construct(
        ReflectionProperty $reflection,
    ) {
        $this->reflection = $reflection;
        $this->propertyName = $reflection->getName();
        $this->parentClass = $reflection->getDeclaringClass();
        $this->class = $this->parentClass->getName();
        $this->classInfo = ClassInfo::fromString($this->class);
        $this->typeInfoAdapter = $this->makeAdapter();
    }

    public function getName() : string {
        return $this->propertyName;
    }

    public function getTypeDetails() : TypeDetails {
        return $this->typeInfoAdapter->getPropertyTypeDetails();
    }

    public function getDescription(): string {
        return Descriptions::forProperty($this->class, $this->propertyName);
    }

    public function isNullable() : bool {
        return $this->typeInfoAdapter->isPropertyNullable();
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
        if ($this->hasMutatorCandidates($this->propertyName)) {
            $mutatorParam = $this->getMutatorParam($this->propertyName);
            if ($mutatorParam === null) {
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

    public function getClass() : string {
        return $this->class;
    }

    private function constructorParams() : array {
        if (!isset($this->constructorParams)) {
            $this->constructorParams = $this->classInfo->getConstructorInfo()->getParameterNames();
        }
        return $this->constructorParams;
    }

    public function hasDefaultValue() : bool {
        return $this->reflection->hasDefaultValue();
    }

    public function getConstructorParam(string $propertyName) : ParameterInfo {
        return $this->classInfo->getConstructorInfo()->getParameter($propertyName);
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////////

    private function makeAdapter() : CanGetPropertyType {
        // Symfony 7+ uses TypeInfo component, Symfony 6 uses PropertyInfo Type class
        $useV7Adapter = class_exists("Symfony\Component\TypeInfo\Type");

        return match(true) {
            $useV7Adapter => new PropertyInfoV7Adapter(
                class: $this->class,
                propertyName: $this->propertyName,
            ),
            default => new PropertyInfoV6Adapter(
                class: $this->class,
                propertyName: $this->propertyName,
            ),
        };
    }

    private function matchesConstructorParam() : bool {
        return in_array($this->propertyName, $this->constructorParams(), true);
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
            $returnType = $method->getReturnType();
            if ($returnType instanceof \ReflectionNamedType && 'void' !== $returnType->getName()) {
                continue; // Skip methods that do not return void
            }
            return true;
        }
        return $this->hasMutatorCandidates = false;
    }

    /** @phpstan-ignore-next-line - method is intentionally unused */
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
            $returnType = $method->getReturnType();
            if ($returnType instanceof \ReflectionNamedType && 'void' === $returnType->getName()) {
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
}

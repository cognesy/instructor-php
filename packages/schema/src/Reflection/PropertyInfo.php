<?php declare(strict_types=1);

namespace Cognesy\Schema\Reflection;

use Cognesy\Schema\TypeInfo;
use Cognesy\Schema\Utils\AttributeUtils;
use Cognesy\Schema\Utils\Descriptions;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\TypeInfo\Type;

class PropertyInfo
{
    private ReflectionProperty $reflection;
    /** @var class-string */
    private string $class;
    private string $propertyName;
    private ReflectionClass $parentClass;
    private static ?PropertyInfoExtractor $extractor = null;

    private ?Type $resolvedType = null;

    public static function fromName(string $class, string $property) : self {
        return self::fromReflection(new ReflectionProperty($class, $property));
    }

    public static function fromReflection(ReflectionProperty $reflection) : self {
        return new self($reflection);
    }

    private function __construct(ReflectionProperty $reflection) {
        $this->reflection = $reflection;
        $this->propertyName = $reflection->getName();
        $this->parentClass = $reflection->getDeclaringClass();
        $this->class = $this->parentClass->getName();
    }

    public function getName() : string {
        return $this->propertyName;
    }

    public function getType() : Type {
        return TypeInfo::normalize($this->resolvedType());
    }

    public function getDescription() : string {
        return Descriptions::forProperty($this->class, $this->propertyName);
    }

    public function isNullable() : bool {
        $nativeType = $this->reflection->getType();
        if ($nativeType !== null && $nativeType->allowsNull()) {
            return true;
        }

        return $this->resolvedType()->isNullable();
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

    public function isReadOnly() : bool {
        return $this->reflection->isReadOnly();
    }

    public function isStatic() : bool {
        return $this->reflection->isStatic();
    }

    public function isDeserializable() : bool {
        return $this->isPublic()
            || $this->constructorParameter() !== null
            || $this->setterParameter() !== null;
    }

    public function isRequired() : bool {
        $constructorParameter = $this->constructorParameter();
        if ($constructorParameter !== null) {
            if ($constructorParameter->allowsNull()) {
                return false;
            }
            return !$constructorParameter->isDefaultValueAvailable();
        }

        if ($this->isPublic()) {
            return !$this->isNullable();
        }

        $setterParameter = $this->setterParameter();
        if ($setterParameter === null) {
            return false;
        }

        if ($this->isNullable()) {
            return false;
        }

        if ($setterParameter->allowsNull()) {
            return false;
        }

        return !$setterParameter->isDefaultValueAvailable();
    }

    public function getClass() : string {
        return $this->class;
    }

    private function resolvedType() : Type {
        if ($this->resolvedType !== null) {
            return $this->resolvedType;
        }

        $this->resolvedType = self::extractor()->getType($this->class, $this->propertyName) ?? Type::mixed();
        return $this->resolvedType;
    }

    private static function extractor() : PropertyInfoExtractor {
        if (self::$extractor !== null) {
            return self::$extractor;
        }

        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        self::$extractor = new PropertyInfoExtractor(
            [$reflectionExtractor],
            [new PhpStanExtractor(), $phpDocExtractor, $reflectionExtractor],
            [$phpDocExtractor],
            [$reflectionExtractor],
            [$reflectionExtractor],
        );

        return self::$extractor;
    }

    private function constructorParameter() : ?\ReflectionParameter {
        $constructor = $this->parentClass->getConstructor();
        if ($constructor === null) {
            return null;
        }

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->getName() === $this->propertyName) {
                return $parameter;
            }
        }

        return null;
    }

    private function setterParameter() : ?ReflectionParameter {
        $setter = $this->setterMethod();
        if ($setter === null) {
            return null;
        }

        return $setter->getParameters()[0] ?? null;
    }

    private function setterMethod() : ?ReflectionMethod {
        $methodName = 'set' . ucfirst($this->propertyName);
        if (!$this->parentClass->hasMethod($methodName)) {
            return null;
        }

        $method = $this->parentClass->getMethod($methodName);
        if (!$method->isPublic()) {
            return null;
        }

        if ($method->getNumberOfParameters() !== 1) {
            return null;
        }

        $returnType = $method->getReturnType();
        if ($returnType !== null && (!$returnType instanceof \ReflectionNamedType || $returnType->getName() !== 'void')) {
            return null;
        }

        return $method;
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Schema\Reflection;

use Cognesy\Schema\Data\TypeDetails;
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
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeIdentifier;

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

    public function getTypeDetails() : TypeDetails {
        return TypeDetails::fromPhpDocTypeString($this->typeToString($this->resolvedType()));
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

    private function typeToString(Type $type) : string {
        $types = [];
        if ($type->isIdentifiedBy(TypeIdentifier::INT)) {
            $types[] = TypeDetails::PHP_INT;
        }
        if ($type->isIdentifiedBy(TypeIdentifier::FLOAT)) {
            $types[] = TypeDetails::PHP_FLOAT;
        }
        if ($type->isIdentifiedBy(TypeIdentifier::STRING)) {
            $types[] = TypeDetails::PHP_STRING;
        }
        if ($type->isIdentifiedBy(TypeIdentifier::BOOL)) {
            $types[] = TypeDetails::PHP_BOOL;
        }
        if ($type->isIdentifiedBy(TypeIdentifier::ARRAY) || $type->isIdentifiedBy(TypeIdentifier::ITERABLE)) {
            $types[] = $this->collectionOrArrayType($type);
        }
        if ($type->isIdentifiedBy(TypeIdentifier::OBJECT)) {
            $className = $this->objectClassName($type);
            if ($className !== null) {
                $types[] = $className;
            }
        }

        if ($types === []) {
            return TypeDetails::PHP_MIXED;
        }

        return implode('|', $types);
    }

    private function collectionOrArrayType(Type $type) : string {
        $valueType = $this->collectionValueType($type);
        if ($valueType === null) {
            return TypeDetails::PHP_ARRAY;
        }

        return $this->arrayTypeString($valueType);
    }

    private function arrayTypeString(Type $type) : string {
        if ($type->isIdentifiedBy(TypeIdentifier::INT)) {
            return TypeDetails::PHP_INT . '[]';
        }
        if ($type->isIdentifiedBy(TypeIdentifier::FLOAT)) {
            return TypeDetails::PHP_FLOAT . '[]';
        }
        if ($type->isIdentifiedBy(TypeIdentifier::STRING)) {
            return TypeDetails::PHP_STRING . '[]';
        }
        if ($type->isIdentifiedBy(TypeIdentifier::BOOL)) {
            return TypeDetails::PHP_BOOL . '[]';
        }
        if ($type->isIdentifiedBy(TypeIdentifier::OBJECT)) {
            $className = $this->objectClassName($type);
            if ($className !== null) {
                return $className . '[]';
            }
        }

        return TypeDetails::PHP_ARRAY;
    }

    private function collectionValueType(Type $type) : ?Type {
        if ($type instanceof CollectionType) {
            return $type->getCollectionValueType();
        }

        $baseType = $this->baseType($type);
        if ($baseType !== $type) {
            $resolved = $this->collectionValueType($baseType);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if (!$type instanceof UnionType) {
            return null;
        }

        foreach ($type->getTypes() as $unionType) {
            $resolved = $this->collectionValueType($unionType);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function baseType(Type $type) : Type {
        if (method_exists(Type::class, 'getBaseType')) {
            $baseType = $type->getBaseType();
            if ($baseType instanceof Type && $baseType !== $type) {
                return $baseType;
            }
        }

        $unwrapped = $type;
        while ($unwrapped instanceof WrappingTypeInterface) {
            $next = $unwrapped->getWrappedType();
            if (!$next instanceof Type || $next === $unwrapped) {
                break;
            }
            $unwrapped = $next;
        }

        return $unwrapped;
    }

    private function objectClassName(Type $type) : ?string {
        $baseType = $this->baseType($type);
        if (method_exists($baseType, 'getClassName')) {
            /** @var string $className */
            $className = $baseType->getClassName();
            return $className;
        }

        if (!$baseType instanceof UnionType) {
            return null;
        }

        foreach ($baseType->getTypes() as $unionType) {
            $className = $this->objectClassName($unionType);
            if ($className !== null) {
                return $className;
            }
        }

        return null;
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
        if ($returnType instanceof \ReflectionNamedType && $returnType->getName() !== 'void') {
            return null;
        }

        return $method;
    }
}

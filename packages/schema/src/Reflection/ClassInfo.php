<?php declare(strict_types=1);

namespace Cognesy\Schema\Reflection;

use Cognesy\Schema\Exceptions\ReflectionException;
use Cognesy\Schema\Utils\Descriptions;
use ReflectionClass;
use Symfony\Component\TypeInfo\Type;

class ClassInfo
{
    /** @var class-string */
    private string $class;
    private ReflectionClass $reflectionClass;

    /** @var array<string, PropertyInfo>|null */
    private ?array $properties = null;

    /**
     * @param class-string $class
     */
    private function __construct(string $class) {
        $this->class = $class;
        $this->reflectionClass = new ReflectionClass($this->class);
    }

    public static function fromString(string $class) : self {
        if (!class_exists($class)) {
            throw ReflectionException::classNotFound($class);
        }

        /** @var class-string $class */
        return new self($class);
    }

    public function getClass() : string {
        return $this->class;
    }

    public function getShortName() : string {
        return $this->reflectionClass->getShortName();
    }

    public function getPropertyType(string $property) : Type {
        return $this->getProperty($property)->getType();
    }

    /** @return string[] */
    public function getPropertyNames() : array {
        return array_keys($this->getProperties());
    }

    /** @return array<string, PropertyInfo> */
    public function getProperties() : array {
        if ($this->properties !== null) {
            return $this->properties;
        }

        $result = [];
        foreach ($this->reflectionClass->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $result[$property->getName()] = PropertyInfo::fromReflection($property);
        }

        $this->properties = $result;
        return $result;
    }

    public function getProperty(string $name) : PropertyInfo {
        $property = $this->getProperties()[$name] ?? null;
        if ($property === null) {
            throw ReflectionException::propertyNotFound($name, $this->class);
        }

        return $property;
    }

    public function getPropertyDescription(string $property) : string {
        return $this->getProperty($property)->getDescription();
    }

    public function hasProperty(string $property) : bool {
        return isset($this->getProperties()[$property]);
    }

    public function isPublic(string $property) : bool {
        return $this->hasProperty($property) && $this->getProperty($property)->isPublic();
    }

    public function isNullable(?string $property = null) : bool {
        if ($property === null) {
            return false;
        }

        return $this->getProperty($property)->isNullable();
    }

    public function isReadOnly(string $property) : bool {
        return $this->getProperty($property)->isReadOnly();
    }

    public function getClassDescription() : string {
        return Descriptions::forClass($this->class);
    }

    /** @return string[] */
    public function getRequiredProperties() : array {
        $required = [];
        foreach ($this->getProperties() as $property) {
            if ($property->isRequired()) {
                $required[] = $property->getName();
            }
        }

        return $required;
    }

    public function isEnum() : bool {
        return $this->reflectionClass->isEnum();
    }

    public function isBacked() : bool {
        return $this->isEnum() && is_subclass_of($this->class, \BackedEnum::class);
    }

    public function enumBackingType() : string {
        if (!$this->isBacked()) {
            return '';
        }

        /** @var class-string<\UnitEnum> $enumClass */
        $enumClass = $this->class;
        $enum = new \ReflectionEnum($enumClass);
        $backingType = $enum->getBackingType();
        if (!$backingType instanceof \ReflectionNamedType) {
            return '';
        }

        return $backingType->getName();
    }

    public function implementsInterface(string $interface) : bool {
        return in_array($interface, class_implements($this->class), true);
    }

    /**
     * @param array<callable(PropertyInfo): bool> $filters
     * @return string[]
     */
    public function getFilteredPropertyNames(array $filters) : array {
        return array_keys($this->getFilteredProperties($filters));
    }

    /**
     * @param array<callable(PropertyInfo): bool> $filters
     * @return array<string, PropertyInfo>
     */
    public function getFilteredProperties(array $filters) : array {
        $properties = $this->getProperties();
        foreach ($filters as $filter) {
            if (!is_callable($filter)) {
                throw ReflectionException::invalidFilter();
            }
            $properties = array_filter($properties, $filter);
        }

        return $properties;
    }
}

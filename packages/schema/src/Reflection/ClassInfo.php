<?php
namespace Cognesy\Schema\Reflection;

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Utils\Descriptions;
use Exception;
use ReflectionClass;

class ClassInfo {
    protected string $class;
    protected ReflectionClass $reflectionClass;
    protected array $propertyInfos = [];
    protected bool $isNullable = false;

    protected function __construct(string $class) {
        // if class name starts with ?, remove it
        if (str_starts_with($class, '?')) {
            $class = substr($class, 1);
            $this->isNullable = true;
        }
        $this->class = $class;
        $this->reflectionClass = new ReflectionClass($class);
    }

    public static function fromString(string $class) : self {
        return match(true) {
            // is any enum class
            class_exists($class) && is_subclass_of($class, \BackedEnum::class) => new EnumInfo($class),
            class_exists($class) => new ClassInfo($class),
            default => throw new Exception("Cannot create ClassInfo for `$class`"),
        };
    }

    public function getClass() : string {
        return $this->class;
    }

    public function getShortName() : string {
        return $this->reflectionClass->getShortName();
    }

    public function getPropertyTypeDetails(string $property): TypeDetails {
        return $this->getProperty($property)->getTypeDetails();
    }

    /** @return string[] */
    public function getPropertyNames() : array {
        return array_keys($this->getProperties());
    }

    /** @return PropertyInfo[] */
    public function getProperties() : array {
        if (empty($this->propertyInfos)) {
            $this->propertyInfos = $this->makePropertyInfos();
        }
        return $this->propertyInfos;
    }

    public function getProperty(string $name) : PropertyInfo {
        $properties = $this->getProperties();
        if (!isset($properties[$name])) {
            throw new \Exception("Property `$name` not found in class `$this->class`.");
        }
        return $properties[$name];
    }

    public function getPropertyDescription(string $property): string {
        return $this->getProperty($property)->getDescription();
    }

    public function hasProperty(string $property) : bool {
        $properties = $this->getProperties();
        return isset($properties[$property]);
    }

    public function isPublic(string $property) : bool {
        if (!$this->hasProperty($property)) {
            return false;
        }
        return $this->getProperty($property)->isPublic();
    }

    public function isNullable(?string $property = null) : bool {
        if ($property === null) {
            return $this->isNullable;
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
        $properties = $this->getProperties();
        if (empty($properties)) {
            return [];
        }
        $required = [];
        foreach ($properties as $property) {
            if (!$property->isRequired()) {
                continue;
            }
            $required[] = $property->getName();
        }
        return $required;
    }

    public function isEnum() : bool {
        return false;
    }

    public function isBackedEnum() : bool {
        return false;
    }

    public function implementsInterface(string $interface) : bool {
        if (!class_exists($this->class)) {
            return false;
        }
        return in_array($interface, class_implements($this->class));
    }

    // CONSTRUCTOR ///////////////////////////////////////////////////////////////

    public function getConstructorInfo(): ConstructorInfo {
        return ConstructorInfo::fromReflectionClass($this->reflectionClass);
    }

    public function hasConstructor(): bool {
        return $this
            ->getConstructorInfo()
            ->hasConstructor();
    }

    // FILTERING /////////////////////////////////////////////////////////////////

    /**
     * @param ClassInfo $classInfo
     * @param array<callable> $filters
     * @return array<string>
     */
    public function getFilteredPropertyNames(array $filters) : array {
        return array_keys($this->getFilteredPropertyData(
            filters: $filters,
            extractor: fn(PropertyInfo $property) => $property->getName()
        ));
    }

    /**
     * @param ClassInfo $classInfo
     * @param array<callable> $filters
     * @return array<PropertyInfo>
     */
    public function getFilteredProperties(array $filters) : array {
        return $this->filterProperties($filters);
    }

    // INTERNAL /////////////////////////////////////////////////////////////////

    /**
     * @param callable[] $filters
     * @return PropertyInfo[]
     */
    protected function filterProperties(array $filters) : array {
        $propertyInfos = $this->getProperties();
        foreach($filters as $filter) {
            if (!is_callable($filter)) {
                throw new Exception("Filter must be a callable.");
            }
            $propertyInfos = array_filter($propertyInfos, $filter);
        }
        return $propertyInfos;
    }

    /** @return PropertyInfo[] */
    protected function makePropertyInfos() : array {
        $properties = $this->reflectionClass->getProperties() ?? [];
        $info = [];
        foreach ($properties as $property) {
            $info[$property->name] = PropertyInfo::fromReflection($property);
        }
        return $info;
    }

    /**
     * @param ClassInfo $classInfo
     * @return array<string, PropertyInfo>
     */
    protected function getFilteredPropertyData(array $filters, callable $extractor) : array {
        return array_map(
            callback: fn(PropertyInfo $property) => $extractor($property),
            array: $this->filterProperties($filters),
        );
    }
}

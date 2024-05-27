<?php
namespace Cognesy\Instructor\Schema\Utils;

use Cognesy\Instructor\Schema\Attributes\Description;
use Cognesy\Instructor\Schema\Attributes\Instructions;
use ReflectionClass;
use ReflectionEnum;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;

class ClassInfo {
    private PropertyInfoExtractor $extractor;
    private string $class;
    private ReflectionClass $reflectionClass;
    private ReflectionEnum $reflectionEnum;
    private array $propertyInfos = [];

    public function __construct(string $class) {
        $this->class = $class;
        $this->reflectionClass = new ReflectionClass($class);
        if ($this->isEnum()) {
            $this->reflectionEnum = new ReflectionEnum($class);
        }
    }

    public function getShortName() : string {
        return $this->reflectionClass->getShortName();
    }

    /** @return Type[] */
    public function getTypes(string $property) : array {
        return $this->getProperty($property)->getTypes();
    }

    public function getType(string $property): Type {
        return $this->getProperty($property)->getType();
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

    public function isNullable(string $property) : bool {
        return $this->getProperty($property)->isNullable();
    }

    public function isReadOnly(string $property) : bool {
        return $this->getProperty($property)->isReadOnly();
    }

    public function getClassDescription() : string {
        $reflection = $this->reflectionClass;

        // get #[Description] attributes
        $descriptions = array_merge(
            AttributeUtils::getValues($reflection, Description::class, 'text'),
            AttributeUtils::getValues($reflection, Instructions::class, 'text'),
        );

        // get class description from PHPDoc
        $phpDocDescription = DocstringUtils::descriptionsOnly($reflection->getDocComment());
        if ($phpDocDescription) {
            $descriptions[] = $phpDocDescription;
        }

        return trim(implode('\n', array_filter($descriptions)));
    }

    /** @return string[] */
    public function getRequiredProperties() : array {
        $properties = $this->getProperties();
        if (empty($properties)) {
            return [];
        }
        $required = [];
        foreach ($properties as $property) {
            if (!$property->isPublic()) {
                continue;
            }
            if (!$property->isNullable()) {
                $required[] = $property->getName();
            }
        }
        return $required;
    }

    public function isEnum() : bool {
        return $this->reflectionClass->isEnum();
    }

    public function isBackedEnum() : bool {
        return !isset($this->reflectionEnum)
            ? false
            : $this->reflectionEnum->isBacked();
    }

    public function enumBackingType() : string {
        return !isset($this->reflectionEnum)
            ? throw new \Exception("Not an enum")
            : $this->reflectionEnum->getBackingType()?->getName();
    }

    /** @return string[]|int[] */
    public function enumValues() : array {
        $enum = !isset($this->reflectionEnum)
            ? throw new \Exception("Not an enum")
            : $this->reflectionEnum;
        $values = [];
        foreach ($enum->getCases() as $item) {
            $values[] = $item->getValue()->value;
        }
        return $values;
    }

    public function implementsInterface(string $interface) : bool {
        if (!class_exists($this->class)) {
            return false;
        }
        return in_array($interface, class_implements($this->class));
    }

    // INTERNAL /////////////////////////////////////////////////////////////////

    /** @return PropertyInfo[] */
    protected function makePropertyInfos() : array {
        $properties = $this->reflectionClass->getProperties() ?? [];
        $info = [];
        foreach ($properties as $property) {
            $info[$property->name] = new PropertyInfo($property);
        }
        return $info;
    }

    protected function extractor() : PropertyInfoExtractor {
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
}

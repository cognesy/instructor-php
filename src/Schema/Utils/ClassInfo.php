<?php

namespace Cognesy\Instructor\Schema\Utils;

use Cognesy\Instructor\Schema\Attributes\Description;
use Cognesy\Instructor\Schema\Attributes\Instructions;
use ReflectionClass;
use ReflectionEnum;
use ReflectionProperty;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;

class ClassInfo {
    private PropertyInfoExtractor $extractor;

    public function __construct() {
        $this->extractor = $this->makeExtractor();
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

    public function getTypes(string $class, string $property) : array {
        $types = $this->extractor->getTypes($class, $property);
        if (is_null($types)) {
            $types = [new Type(Type::BUILTIN_TYPE_STRING)];
        }
        return $types;
    }

    public function getType(string $class, string $property): Type {
        $propertyTypes = $this->getTypes($class, $property);
        if (!count($propertyTypes)) {
            throw new \Exception("No type found for property: $class::$property");
        }
        if (count($propertyTypes) > 1) {
            throw new \Exception("Unsupported union type found for property: $class::$property");
        }
        return $propertyTypes[0];
    }

    public function getProperties(string $class) : array {
        return $this->extractor->getProperties($class) ?? [];
    }

    public function getClassDescription(string $class) : string {
        $reflection = new ReflectionClass($class);

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

    public function getPropertyDescription(string $class, string $property): string {
        // get #[Description] attributes
        $reflection = new ReflectionProperty($class, $property);
        $descriptions = array_merge(
            AttributeUtils::getValues($reflection, Description::class, 'text'),
            AttributeUtils::getValues($reflection, Instructions::class, 'text'),
        );

        // get property description from PHPDoc
        $descriptions[] = $this->extractor->getShortDescription($class, $property);
        $descriptions[] = $this->extractor->getLongDescription($class, $property);

        return trim(implode('\n', array_filter($descriptions)));
    }

    public function getRequiredProperties(string $class) : array {
        $properties = $this->getProperties($class);
        if (empty($properties)) {
            return [];
        }
        $required = [];
        foreach ($properties as $property) {
            if (!$this->isPublic($class, $property)) {
                continue;
            }
            if (!$this->isNullable($class, $property)) {
                $required[] = $property;
            }
        }
        return $required;
    }

    public function isPublic(string $class, string $property) : bool {
        return (new ReflectionClass($class))->getProperty($property)?->isPublic();
    }

    public function isNullable(string $class, string $property) : bool {
        $types = $this->extractor->getTypes($class, $property);
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

    public function isEnum(string $class) : bool {
        return (new ReflectionClass($class))->isEnum();
    }

    public function isBackedEnum(string $class) : bool {
        return (new ReflectionEnum($class))->isBacked();
    }

    public function enumBackingType(string $class) : string {
        return (new ReflectionEnum($class))->getBackingType()?->getName();
    }

    public function enumValues(string $class) : array {
        $enum = new ReflectionEnum($class);
        $values = [];
        foreach ($enum->getCases() as $item) {
            $values[] = $item->getValue()->value;
        }
        return $values;
    }

    public function implementsInterface(string $anyType, string $interface) : bool {
        if (!class_exists($anyType)) {
            return false;
        }
        return in_array($interface, class_implements($anyType));
    }
}

<?php

namespace Cognesy\Instructor\Schema\Utils;

use Cognesy\Instructor\Schema\Attributes\Description;
use Cognesy\Instructor\Schema\Attributes\Instructions;
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
    private string $property;
    private PropertyInfoExtractor $extractor;
    private array $types;

    static public function fromName(string $class, string $property) : PropertyInfo {
        $reflection = new ReflectionProperty($class, $property);
        return new PropertyInfo($reflection);
    }

    public function __construct(ReflectionProperty $reflection) {
        $this->reflection = $reflection;
        $this->class = $reflection->getDeclaringClass()->getName();
        $this->property = $reflection->getName();
    }

    public function getName() : string {
        return $this->property;
    }

    public function getTypes() : array {
        if (!isset($this->types)) {
            $this->types = $this->makeTypes();
        }
        return $this->types;
    }

    public function getType(): Type {
        $propertyTypes = $this->getTypes();
        if (!count($propertyTypes)) {
            throw new \Exception("No type found for property: $this->class::$this->property");
        }
        if (count($propertyTypes) > 1) {
            throw new \Exception("Unsupported union type found for property: $this->class::$this->property");
        }
        return $propertyTypes[0];
    }

    public function getTypeName() : string {
        $type = $this->getType();
        $builtInType = $type->getBuiltinType();
        return match($builtInType) {
            Type::BUILTIN_TYPE_INT => 'int',
            Type::BUILTIN_TYPE_FLOAT => 'float',
            Type::BUILTIN_TYPE_STRING => 'string',
            Type::BUILTIN_TYPE_BOOL => 'bool',
            Type::BUILTIN_TYPE_ARRAY => $this->getArrayTypeName($type),
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
            AttributeUtils::getValues($this->reflection, Description::class, 'text'),
            AttributeUtils::getValues($this->reflection, Instructions::class, 'text'),
        );

        // get property description from PHPDoc
        $descriptions[] = $this->extractor()->getShortDescription($this->class, $this->property);
        $descriptions[] = $this->extractor()->getLongDescription($this->class, $this->property);

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

    public function isNullable() : bool {
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

    public function isReadOnly() : bool {
        return $this->reflection->isReadOnly();
    }

    public function isStatic() : bool {
        return $this->reflection->isStatic();
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////////

    private function getArrayTypeName(Type $type) : string {
        $valueType = $type->getCollectionValueTypes();
        $valueType = $valueType[0] ?? null;
        if (is_null($valueType)) {
            return 'mixed';
        }
        $builtInType = $valueType->getBuiltinType();
        return match($builtInType) {
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
            default => 'mixed',
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

    protected function makeTypes() : array {
        $types = $this->extractor()->getTypes($this->class, $this->property);
        if (is_null($types)) {
            $types = [new Type(Type::BUILTIN_TYPE_STRING)];
        }
        return $types;
    }
}
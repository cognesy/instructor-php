<?php

namespace Cognesy\Schema\Utils\Compat;

use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Attributes\Instructions;
use Cognesy\Schema\Contracts\CanGetPropertyType;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Factories\TypeDetailsFactory;
use Cognesy\Schema\Utils\AttributeUtils;
use ReflectionProperty;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;

class PropertyInfoV6Adapter implements CanGetPropertyType
{
    private TypeDetailsFactory $typeDetailsFactory;
    private string $class;
    private string $propertyName;
    private ReflectionProperty $reflection;

    private array $types;
    private PropertyInfoExtractor $extractor;

    public function __construct(
        string $class,
        string $propertyName,
        ReflectionProperty $reflection,
        TypeDetailsFactory $typeDetailsFactory,
    ) {
        // if class name starts with ?, remove it
        if (str_starts_with($class, '?')) {
            $class = substr($class, 1);
        }
        $this->class = $class;
        $this->propertyName = $propertyName;
        $this->reflection = $reflection;
        $this->typeDetailsFactory = $typeDetailsFactory;
    }

    public function getPropertyTypeDetails(): TypeDetails {
        $typeName = $this->getPropertyTypeName();
        return $this->typeDetailsFactory->fromTypeName($typeName);
    }

    public function getPropertyTypeName(): string {
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

    public function getPropertyDescription(): string {
        // get #[Description] attributes
        $descriptions = array_merge(
            AttributeUtils::getValues($this->reflection, Description::class, 'text'),
            AttributeUtils::getValues($this->reflection, Instructions::class, 'text'),
        );
        // get property description from PHPDoc
        $descriptions[] = $this->extractor()->getShortDescription($this->class, $this->propertyName);
        $descriptions[] = $this->extractor()->getLongDescription($this->class, $this->propertyName);

        return trim(implode('\n', array_filter($descriptions)));
    }

    public function isPropertyNullable(): bool {
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

    // INTERNAL /////////////////////////////////////////////////////////////////////

    private function getType(): Type {
        $propertyTypes = $this->getTypes();
        if (!count($propertyTypes)) {
            throw new \Exception("No type found for property: $this->class::$this->propertyName");
        }
        if (count($propertyTypes) > 1) {
            throw new \Exception("Unsupported union type found for property: $this->class::$this->propertyName");
        }
        return $propertyTypes[0];
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

    private function getTypes(): array {
        if (!isset($this->types)) {
            $this->types = $this->makeTypes();
        }
        return $this->types;
    }

    private function makeTypes(): array {
        $types = $this->extractor()->getTypes($this->class, $this->propertyName);
        if (is_null($types)) {
            $types = [new Type(Type::BUILTIN_TYPE_STRING)];
        }
        return $types;
    }

    private function extractor(): PropertyInfoExtractor {
        if (!isset($this->extractor)) {
            $this->extractor = $this->makeExtractor();
        }
        return $this->extractor;
    }

    private function makeExtractor(): PropertyInfoExtractor {
        // initialize extractor instance
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        return new PropertyInfoExtractor(
            [$reflectionExtractor],
            [
                new PhpStanExtractor(),
                $phpDocExtractor,
                $reflectionExtractor
            ],
            [$phpDocExtractor],
            [$reflectionExtractor],
            [$reflectionExtractor],
        );
    }
}

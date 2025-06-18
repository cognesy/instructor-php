<?php

namespace Cognesy\Schema\Utils\Compat;

use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Attributes\InputField;
use Cognesy\Schema\Attributes\Instructions;
use Cognesy\Schema\Attributes\OutputField;
use Cognesy\Schema\Contracts\CanGetPropertyType;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Factories\TypeDetailsFactory;
use Cognesy\Schema\Utils\AttributeUtils;
use ReflectionProperty;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

class PropertyInfoV7Adapter implements CanGetPropertyType
{
    private TypeDetailsFactory $typeDetailsFactory;
    private string $class;
    private string $propertyName;

    private ?Type $type = null;
    private PropertyInfoExtractor $extractor;
    private ReflectionProperty $reflection;

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

    public function getPropertyTypeName(): string {
        $type = $this->getType();
        return match (true) {
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

    public function getPropertyTypeDetails(): TypeDetails {
        $typeName = $this->getPropertyTypeName();
        return $this->typeDetailsFactory->fromTypeName($typeName);
    }

    public function getPropertyDescription(): string {
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

    public function isPropertyNullable(): bool {
        return $this->getType()->isNullable();
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    private function getType(): Type {
        if (!isset($this->type)) {
            $this->type = $this->makeTypes();
        }
        return $this->type;
    }

    private function makeTypes() : Type {
        $type = $this->extractor()->getType($this->class, $this->propertyName);
        if (is_null($type)) {
            $type = Type::mixed();
        }
        return $type;
    }

    private function getCollectionOrArrayType(Type $type): string {
        $valueType = $type->getCollectionValueType();
        if (is_null($valueType)) {
            return 'array';
        }
        return match (true) {
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

    private function makeExtractor() : PropertyInfoExtractor {
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

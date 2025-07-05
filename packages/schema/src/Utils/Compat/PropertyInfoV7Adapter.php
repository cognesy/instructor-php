<?php

namespace Cognesy\Schema\Utils\Compat;

use Cognesy\Schema\Contracts\CanGetPropertyType;
use Cognesy\Schema\Data\TypeDetails;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

class PropertyInfoV7Adapter implements CanGetPropertyType
{
    private string $class;
    private string $propertyName;

    public function __construct(
        string $class,
        string $propertyName,
    ) {
        // if class name starts with ?, remove it
        if (str_starts_with($class, '?')) {
            $class = substr($class, 1);
        }
        $this->class = $class;
        $this->propertyName = $propertyName;
    }

    public function getPropertyTypeDetails(): TypeDetails {
        $types = $this->makeTypes();
        $typeString = $this->typeToString($types);
        return TypeDetails::fromPhpDocTypeString($typeString);
    }

    public function isPropertyNullable(): bool {
        return $this->makeTypes()->isNullable();
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    private function typeToString(Type $type): string {
        $types = [];
        if ($type->isIdentifiedBy(TypeIdentifier::INT)) { $types[] = 'int'; }
        if ($type->isIdentifiedBy(TypeIdentifier::FLOAT)) { $types[] = 'float'; }
        if ($type->isIdentifiedBy(TypeIdentifier::STRING)) { $types[] = 'string'; }
        if ($type->isIdentifiedBy(TypeIdentifier::BOOL)) { $types[] = 'bool'; }
        if ($type->isIdentifiedBy(TypeIdentifier::ARRAY)) { $types[] = $this->getCollectionOrArrayType($type); }
        if ($type->isIdentifiedBy(TypeIdentifier::OBJECT)) { $types[] = $type->getClassName(); }
        if ($type->isIdentifiedBy(TypeIdentifier::ITERABLE)) { $types[] = $this->getCollectionOrArrayType($type); }
        //if ($type->isIdentifiedBy(TypeIdentifier::NULL)) { $types[] = 'null'; }
        if (empty($types)) {
            $types[] = 'mixed';
        }
        return implode('|', $types);
    }

    private function getCollectionOrArrayType(Type $type): string {
        $valueType = $type->getCollectionValueType();
        if (is_null($valueType)) {
            return 'array';
        }
        return $this->arrayTypeToString($valueType);
    }

    private function arrayTypeToString(Type $type) : string {
        $types = [];
        if ($type->isIdentifiedBy(TypeIdentifier::INT)) { $types[] = 'int[]'; }
        if ($type->isIdentifiedBy(TypeIdentifier::FLOAT)) { $types[] = 'float[]'; }
        if ($type->isIdentifiedBy(TypeIdentifier::STRING)) { $types[] = 'string[]'; }
        if ($type->isIdentifiedBy(TypeIdentifier::BOOL)) { $types[] = 'bool[]'; }
        if ($type->isIdentifiedBy(TypeIdentifier::ARRAY)) { $types[] = 'array'; }
        if ($type->isIdentifiedBy(TypeIdentifier::OBJECT)) { $types[] = $type->getClassName(). '[]'; }
        if ($type->isIdentifiedBy(TypeIdentifier::ITERABLE)) { $types[] = 'array'; }
        //if ($type->isIdentifiedBy(TypeIdentifier::NULL)) { $types[] = 'null'; }
        if (empty($types)) {
            $types[] = 'array';
        }
        return implode('|', $types);
    }

    private function makeTypes() : Type {
        $type = $this->makeExtractor()->getType($this->class, $this->propertyName);
        if (is_null($type)) {
            $type = Type::mixed();
        }
        return $type;
    }

    private function makeExtractor() : PropertyInfoExtractor {
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

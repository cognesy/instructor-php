<?php

namespace Cognesy\Schema\Utils\Compat;

use Cognesy\Schema\Contracts\CanGetPropertyType;
use Cognesy\Schema\Data\TypeDetails;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;

class PropertyInfoV6Adapter implements CanGetPropertyType
{
    private string $class;
    private string $propertyName;

    private PropertyInfoExtractor $extractor;

    public function __construct(
        string $class,
        string $propertyName,
    ) {
//dump('prop info v6', $class, $propertyName);
        // if class name starts with ?, remove it
        if (str_starts_with($class, '?')) {
            $class = substr($class, 1);
        }
        $this->class = $class;
        $this->propertyName = $propertyName;
    }

    public function getPropertyTypeDetails(): TypeDetails {
        $type = $this->makeTypes();
        if ($type === null || count($type) === 0) {
            return TypeDetails::mixed();
        }
        $typeString = $this->typesToUnionString($type);
        return TypeDetails::fromPhpDocTypeString($typeString);
    }

    public function isPropertyNullable(): bool {
        $types = $this->makeTypes();
        if (is_null($types)) {
            return true;
        }
        foreach ($types as $type) {
            if ($type->isNullable()) {
                return true;
            }
        }
        return false;
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    /**
     * Convert the types to a string representation.
     *
     * @param Type[] $types
     * @return string
     */
    private function typesToUnionString(array $types): string {
        $result = [];
        foreach ($types as $type) {
            if (in_array($type->getBuiltinType(), TypeDetails::PHP_SCALAR_TYPES)) { $result[] = $type->getBuiltinType(); }
            if ($type->isCollection()) { $result[] = $this->getCollectionOrArrayType($type); }
            if ($type->getBuiltinType() === Type::BUILTIN_TYPE_ARRAY) { $result[] = 'array'; }
            if ($type->getClassName()) { $result[] = $type->getClassName() ?? 'object'; }
            //if ($type->isNullable()) { $result[] = 'null'; }
        }
        return implode('|', $result);
    }

    private function getCollectionOrArrayType(Type $parentType): string {
        $valueTypes = $parentType->getCollectionValueTypes();
        if (is_null($valueTypes)) {
            return 'array';
        }

        $result = [];
        foreach ($valueTypes as $valueType) {
            if (in_array($valueType->getBuiltinType(), TypeDetails::PHP_SCALAR_TYPES)) { $result[] = $valueType->getBuiltinType() . '[]'; }
            if ($valueType->isCollection()) { $result[] = 'array'; } // collection of collections is considered an array
            if ($valueType->getBuiltinType() === Type::BUILTIN_TYPE_ARRAY) { $result[] = 'array'; }
            if ($valueType->getClassName()) { $result[] = $parentType->getClassName()
                ? ($parentType->getClassName() . '[]')
                : 'array';  // collection of unspecified objects is considered an array
            }
            //if ($valueType->isNullable()) { $result[] = 'null'; }
        }
        return implode('|', $result);
    }

    /** @return Type[] */
    private function makeTypes(): ?array {
        $types = $this->extractor()->getTypes($this->class, $this->propertyName);
        if (is_null($types)) {
            $types = null;
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

<?php

namespace Cognesy\Schema\Factories\Traits\TypeDetailsFactory;

use Cognesy\Schema\Data\TypeDetails;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\TypeIdentifier;

trait HandlesResolvers
{
    // TYPE DETAILS RESOLUTION ////////////////////////////////////////////////////////////////

    /**
     * Create TypeDetails from type string
     *
     * @param string $anyType
     * @return TypeDetails
     */
    public function fromTypeName(?string $anyType) : TypeDetails {
        if ($anyType === null) {
            throw new \Exception('Instructor does not support mixed or unspecified property types');
        }

        $normalized = $this->normalizeIfCollection($anyType);
        return match (true) {
            ($normalized == TypeDetails::PHP_OBJECT) => throw new \Exception('Object type must have a class name'),
            ($normalized == TypeDetails::PHP_ENUM) => throw new \Exception('Enum type must have a class'),
            ($normalized == TypeDetails::PHP_COLLECTION) => $this->collectionType($anyType),
            ($normalized == TypeDetails::PHP_ARRAY) => $this->arrayType(),
            (in_array($normalized, TypeDetails::PHP_SCALAR_TYPES)) => $this->scalarType($anyType),
            default => $this->objectType($anyType),
        };
    }

    public function fromTypeInfo(Type $typeInfo) : TypeDetails {
        if ($this->isCollection($typeInfo)) {
            $typeString = (string) $typeInfo;
            $collectionType = $this->toCollectionTypeString($typeString);
            return $this->collectionType($collectionType);
        }
        if ($this->isArray($typeInfo)) {
            return $this->arrayType();
        }
        if ($this->isScalar($typeInfo)) {
            $type = $this->typeInfoToScalar($typeInfo);
            return $this->scalarType($type);
        }
        if ($this->isObject($typeInfo)) {
            $className = $this->toObjectTypeString((string) $typeInfo);
            return $this->objectType($className);
        }
        throw new \Exception('Unsupported type: '.$typeInfo);
    }

    /**
     * Create TypeDetails from object instance
     *
     * @param object $instance
     * @return TypeDetails
     */
    public function fromValue(mixed $anyVar) : TypeDetails {
        $type = TypeDetails::getType($anyVar);
        return match (true) {
            ($type == TypeDetails::PHP_OBJECT) => $this->objectType(get_class($anyVar)),
            ($type == TypeDetails::PHP_ARRAY && $this->allItemsShareType($anyVar)) => $this->collectionType($this->collectionTypeStringFromValues($anyVar)),
            ($type == TypeDetails::PHP_ARRAY) => $this->arrayType(),
            (in_array($type, TypeDetails::PHP_SCALAR_TYPES)) => $this->scalarType($type),
            default => throw new \Exception('Unsupported type: '.$type),
        };
    }

    // INTERNAL ///////////////////////////////////////////////////////////////////////////////

    /**
     * Express Type[] type as array
     */
    private function normalizeIfCollection(string $type) : string {
        return match(true) {
            (substr($type, -2) === '[]') => TypeDetails::PHP_COLLECTION,
            ($type === TypeDetails::PHP_ARRAY) => TypeDetails::PHP_ARRAY,
            (substr($type, 0, 5) === 'array') => TypeDetails::PHP_ARRAY,
            default => $type,
        };
    }

    /**
     * Determine array type from array values
     */
    private function collectionTypeStringFromValues(array $array) : string
    {
        if (empty($array)) {
            throw new \Exception('Array is empty, cannot determine type of elements');
        }
        $nestedType = TypeDetails::getType($array[0]);
        if (in_array($nestedType, TypeDetails::PHP_SCALAR_TYPES)) {
            return "{$nestedType}[]";
        }
        if ($nestedType === TypeDetails::PHP_OBJECT) {
            $nestedClass = get_class($array[0]);
            return "{$nestedClass}[]";
        }
        throw new \Exception('Unsupported array element type: '.$nestedType);
    }

    private function allItemsShareType(array $array) : bool {
        $type = TypeDetails::getType($array[0]);
        foreach ($array as $item) {
            if (TypeDetails::getType($item) !== $type) {
                return false;
            }
        }
        return true;
    }

    private function isScalar(Type $typeInfo) : bool {
        return $typeInfo->isIdentifiedBy(TypeIdentifier::INT, TypeIdentifier::FLOAT, TypeIdentifier::STRING, TypeIdentifier::BOOL);
    }

    private function isObject(Type $typeInfo) : bool {
        return $typeInfo->isIdentifiedBy(TypeIdentifier::OBJECT);
    }

    private function isCollection(Type $typeInfo) : bool {
        if (!($typeInfo instanceof CollectionType)) {
            return false;
        }
        $collectionValueType = $typeInfo->getCollectionValueType();
        if ($collectionValueType === null) {
            return false;
        }
        if ($this->isEnum($collectionValueType)) {
            return true; // enum collection is still a collection
        }
        if ($this->isArray($collectionValueType)) {
            return false; // array is not a collection
        }
        if ($this->isScalar($collectionValueType)) {
            return true; // scalar collection is still a collection
        }
        if ($this->isObject($collectionValueType)) {
            return true; // object collection is still a collection
        }
        return false; // it is not a collection - e.g. just array of mixed types
    }

    private function isArray(Type $typeInfo) : bool {
        return $typeInfo->isIdentifiedBy(TypeIdentifier::ARRAY);
    }

    private function isEnum(Type $typeInfo) : bool {
        return $typeInfo->isIdentifiedBy(TypeIdentifier::OBJECT)
            && $typeInfo->getClassName() !== null
            && is_subclass_of($typeInfo->getClassName(), \BackedEnum::class);
    }

    private function typeInfoToScalar(Type $typeInfo) {
        return match(true) {
            $typeInfo->isIdentifiedBy(TypeIdentifier::INT) => TypeDetails::PHP_INT,
            $typeInfo->isIdentifiedBy(TypeIdentifier::FLOAT) => TypeDetails::PHP_FLOAT,
            $typeInfo->isIdentifiedBy(TypeIdentifier::STRING) => TypeDetails::PHP_STRING,
            $typeInfo->isIdentifiedBy(TypeIdentifier::BOOL) => TypeDetails::PHP_BOOL,
            default => throw new \Exception('Unsupported scalar type: '.$typeInfo),
        };
    }

    private function toObjectTypeString(string $typeString) : string {
        if ($typeString === TypeDetails::PHP_OBJECT) {
            throw new \Exception('Object type must have a class name');
        }
        if ($typeString === TypeDetails::PHP_ENUM) {
            throw new \Exception('Enum type must have a class name');
        }
        $typeString = $this->removeNullable($typeString);
        if ($this->isUnionTypeString($typeString)) {
            $typeString = $this->fromUnionTypeString($typeString);
        }
        if (!class_exists($typeString)) {
            throw new \Exception("Object type class does not exist: `{$typeString}`");
        }
        return $typeString;
    }

    private function toCollectionTypeString(string $typeString) : string {
        $sourceTypeString = $typeString;
        $isNullable = false;
        if (substr($typeString, -2) === '[]') {
            return $typeString;
        }
        if (!substr($typeString, 0, 5) === 'array') {
            throw new \Exception('Unknown collection type string format: '.$sourceTypeString);
        }
        // array<int, string> => int, string
        $typeString = substr($typeString, 6, -1);
        // if there is a comma, take the second part (eg. int, string => string)
        if (strpos($typeString, ',') !== false) {
            $parts = explode(',', $typeString);
            $typeString = trim($parts[1]);
        }
        // if contains ? then remove it (eg. ?int => int)
        if (strpos($typeString, '?') !== false) {
            $isNullable = true;
            $typeString = str_replace('?', '', $typeString);
        }
        $typeString = $this->removeNullable($typeString);
        if ($this->isUnionTypeString($typeString)) {
            $typeString = $this->fromUnionTypeString($typeString);
        }
        // extract type from array<something> so we can return something[]
        if (in_array($typeString, TypeDetails::PHP_SCALAR_TYPES)) {
            return $isNullable ? "?{$typeString}[]" : "{$typeString}[]";
        }
        // fail on nested array (eg. array<int, array<string, int>>)
        if (strpos($typeString, 'array<') !== false) {
            throw new \Exception('Collection type cannot be an array of arrays: '.$sourceTypeString);
        }
        // fail on nested array
        if (substr($typeString, -2) === '[]') {
            throw new \Exception('Collection type cannot be an array of arrays: '.$sourceTypeString);
        }
        if ($typeString === TypeDetails::PHP_OBJECT) {
            throw new \Exception('Collection type must have a class name: '.$sourceTypeString);
        }
        if ($typeString === TypeDetails::PHP_ENUM) {
            throw new \Exception('Collection type must have a class name: '.$sourceTypeString);
        }
        if (!class_exists($typeString)) {
            throw new \Exception("Collection type class does not exist: `{$typeString}` for collection type string: `{$sourceTypeString}`");
        }
        return $isNullable ? "?{$typeString}[]" : "{$typeString}[]";
    }

    private function removeNullable(string $typeString) : string {
        // if union and has null then remove null item: int|null or null|int) => int
        if (strpos($typeString, 'null') !== false) {
            $isNullable = true;
            $parts = explode('|', $typeString);
            // remove null from parts
            $parts = array_filter($parts, fn($part) => trim($part) !== 'null');
            $typeString = trim(implode('|', $parts));
        }
        return $typeString;
    }

    private function isNullable(string $typeString) : bool {
        return strpos($typeString, 'null') !== false
            || strpos($typeString, '?') !== false;
    }

    private function isUnionTypeString(string $typeString) : bool {
        return strpos($typeString, '|') !== false;
    }

    private function fromUnionTypeString(string $typeString) : string {
        // if contains union type (eg. int|string), take the first part (eg. int)
        $parts = explode('|', $typeString);
        $typeString = trim($parts[0]);
        return $typeString;
    }
}
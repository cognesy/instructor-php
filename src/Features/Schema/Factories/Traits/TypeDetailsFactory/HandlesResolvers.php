<?php

namespace Cognesy\Instructor\Features\Schema\Factories\Traits\TypeDetailsFactory;

use Cognesy\Instructor\Features\Schema\Data\TypeDetails;
use Symfony\Component\PropertyInfo\Type;

trait HandlesResolvers
{
    // TYPE DETAILS RESOLUTION ////////////////////////////////////////////////////////////////

    /**
     * Create TypeDetails from type string
     *
     * @param string $anyType
     * @return \Cognesy\Instructor\Features\Schema\Data\TypeDetails
     */
    public function fromTypeName(string $anyType) : TypeDetails {
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

    /**
     * Create TypeDetails from PropertyInfo
     *
     * @param Type $propertyInfo
     * @return TypeDetails
     */
    public function fromPropertyInfo(Type $propertyInfo) : TypeDetails {
        $class = $propertyInfo->getClassName();
        $type = $propertyInfo->getBuiltinType();
        $collectionType = ($propertyInfo->getBuiltinType() === 'array') ? $this->collectionTypeString($propertyInfo) : '';
        return match(true) {
            (in_array($type, TypeDetails::PHP_OBJECT_TYPES)) => $this->fromTypeName($class),
            (in_array($type, TypeDetails::PHP_SCALAR_TYPES)) => $this->scalarType($type),
            ($type === TypeDetails::PHP_ARRAY && $this->isCollection($collectionType)) => $this->collectionType($collectionType),
            ($type === TypeDetails::PHP_ARRAY) => $this->arrayType(),
            ($class !== null) => $this->objectType($class),
            default => throw new \Exception('Unsupported type: '.$type),
        };
    }

    /**
     * Create TypeDetails from object instance
     *
     * @param object $instance
     * @return \Cognesy\Instructor\Features\Schema\Data\TypeDetails
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
     * Express array type as <type>[]
     */
    private function collectionTypeString(Type $propertyInfo) : string {
        $collectionValueTypes = $propertyInfo->getCollectionValueTypes();
        $collectionValueType = $collectionValueTypes[0] ?? null;
        if ($collectionValueType === null) {
            return '';
        }
        $nestedType = $collectionValueType->getBuiltinType() ?? '';
        $nestedClass = $collectionValueType->getClassName() ?? '';
        return empty($nestedClass) ? "{$nestedType}[]" : "{$nestedClass}[]";
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

    private function isCollection(string $typeSpec) : bool {
        return match(true) {
            (substr($typeSpec, -2) === '[]') => true,
            default => false,
        };
    }
}
<?php

namespace Cognesy\Instructor\Schema\Factories;

use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Utils\ClassInfo;
use Cognesy\Instructor\Schema\Utils\PropertyInfo;
use Symfony\Component\PropertyInfo\Type;

/**
 * Factory for creating TypeDetails from type strings or PropertyInfo Type objects
 */
class TypeDetailsFactory
{
    // TYPE DETAILS RESOLUTION ////////////////////////////////////////////////////////////////

    /**
     * Create TypeDetails from type string
     *
     * @param string $anyType
     * @return \Cognesy\Instructor\Schema\Data\TypeDetails
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
     * @return \Cognesy\Instructor\Schema\Data\TypeDetails
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

    // TYPE DETAILS CREATION //////////////////////////////////////////////////////////////////

    /**
     * Create TypeDetails for atom (scalar) type
     *
     * @param string $type
     * @return TypeDetails
     */
    public function scalarType(string $type) : TypeDetails {
        if (!in_array($type, TypeDetails::PHP_SCALAR_TYPES)) {
            throw new \Exception('Unsupported scalar type: '.$type);
        }
        return new TypeDetails(
            type: $type,
            docString: $type
        );
    }

    /**
     * Create TypeDetails for array type
     *
     * @param string $typeSpec
     * @return \Cognesy\Instructor\Schema\Data\TypeDetails
     */
    public function arrayType(string $typeSpec = '') : TypeDetails {
        if ($this->isArrayShape($typeSpec)) {
            return $this->arrayShapeType($typeSpec);
        }
        return new TypeDetails(
            type: TypeDetails::PHP_ARRAY,
            nestedType: null,
            docString: $typeSpec
        );
    }

    /**
     * Create TypeDetails for array type
     *
     * @param string $typeSpec
     * @return \Cognesy\Instructor\Schema\Data\TypeDetails
     */
    public function collectionType(string $typeSpec) : TypeDetails {
        $typeName = $this->getCollectionType($typeSpec);
        $nestedType = match (true) {
            ($typeName == TypeDetails::PHP_MIXED) => throw new \Exception('Mixed type not supported'),
            ($typeName == TypeDetails::PHP_ARRAY) => throw new \Exception('You have not specified array element type'),
            (in_array($typeName, TypeDetails::PHP_SCALAR_TYPES)) => $this->scalarType($typeName),
            default => $this->objectType($typeName),
        };
        return new TypeDetails(
            type: TypeDetails::PHP_COLLECTION,
            nestedType: $nestedType,
            docString: $typeSpec
        );
    }

    /**
     * Create TypeDetails for array type
     *
     * @param string $typeSpec
     * @return \Cognesy\Instructor\Schema\Data\TypeDetails
     */
    public function arrayShapeType(string $typeSpec) : TypeDetails {
        throw new \Exception('Array shape type not supported yet');
    }

    /**
     * Create TypeDetails for object type
     *
     * @param string $typeName
     * @return TypeDetails
     */
    public function objectType(string $typeName) : TypeDetails {
        if ((new ClassInfo($typeName))->isEnum()) {
            return $this->enumType($typeName);
        }
        $instance = new TypeDetails(
            type: TypeDetails::PHP_OBJECT,
            class: $typeName,
            docString: $typeName
        );
        return $instance;
    }

    /**
     * Create TypeDetails for enum type
     *
     * @param string $typeName
     * @return TypeDetails
     */
    public function enumType(string $typeName, string $enumType = null, array $enumValues = null) : TypeDetails {
        $classInfo = new ClassInfo($typeName);
        // enum specific
        if (!$classInfo->isBackedEnum()) {
            throw new \Exception('Enum must be backed by a string or int');
        }
        $backingType = $enumType ?? $classInfo->enumBackingType();
        if (!in_array($backingType, TypeDetails::PHP_ENUM_TYPES)) {
            throw new \Exception('Enum must be backed by a string or int');
        }
        return new TypeDetails(
            type: TypeDetails::PHP_ENUM,
            class: $typeName,
            enumType: $backingType,
            enumValues: $enumValues ?? $classInfo->enumValues(),
            docString: $typeName
        );
    }

    // INTERNAL ///////////////////////////////////////////////////////////////////////////////

    /**
     * Extract array type from type string
     */
    private function getCollectionType(string $typeSpec) : string {
        if (substr($typeSpec, -2) !== '[]') {
            return $typeSpec;
        }
        return substr($typeSpec, 0, -2);
    }

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
        $collectionValueType = $propertyInfo->getCollectionValueTypes()[0];
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

    private function isArrayShape(string $typeSpec) : bool {
        // TODO: not supported yet
        return false;
    }

    private function isCollection(string $typeSpec) : bool {
        return match(true) {
            (substr($typeSpec, -2) === '[]') => true,
            default => false,
        };
    }
}

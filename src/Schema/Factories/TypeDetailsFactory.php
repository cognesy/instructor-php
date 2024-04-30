<?php

namespace Cognesy\Instructor\Schema\Factories;

use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Utils\ClassInfo;
use Symfony\Component\PropertyInfo\Type;

/**
 * Factory for creating TypeDetails from type strings or PropertyInfo Type objects
 */
class TypeDetailsFactory
{
    private ClassInfo $classInfo;

    public function __construct() {
        $this->classInfo = new ClassInfo;
    }

    /**
     * Create TypeDetails from type string
     *
     * @param string $anyType
     * @return \Cognesy\Instructor\Schema\Data\TypeDetails
     */
    public function fromTypeName(string $anyType) : TypeDetails {
        $normalized = $this->normalizeIfArray($anyType);
        return match (true) {
            ($normalized == TypeDetails::PHP_OBJECT) => throw new \Exception('Object type must have a class name'),
            ($normalized == TypeDetails::PHP_ENUM) => throw new \Exception('Enum type must have a class'),
            ($normalized == TypeDetails::PHP_ARRAY) => $this->arrayType($anyType),
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
        return match(true) {
            (in_array($type, TypeDetails::PHP_OBJECT_TYPES)) => $this->fromTypeName($class),
            (in_array($type, TypeDetails::PHP_SCALAR_TYPES)) => $this->scalarType($type),
            ($type === TypeDetails::PHP_ARRAY) => $this->arrayType($this->arrayTypeString($propertyInfo)),
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
            ($type == TypeDetails::PHP_ARRAY) => $this->arrayType($this->arrayTypeStringFromValues($anyVar)),
            (in_array($type, TypeDetails::PHP_SCALAR_TYPES)) => $this->scalarType($type),
            default => throw new \Exception('Unsupported type: '.$type),
        };
    }

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
        return new TypeDetails($type);
    }

    /**
     * Create TypeDetails for array type
     *
     * @param string $typeSpec
     * @return \Cognesy\Instructor\Schema\Data\TypeDetails
     */
    public function arrayType(string $typeSpec) : TypeDetails {
        $typeName = $this->getArrayType($typeSpec);
        $nestedType = match (true) {
            ($typeName == TypeDetails::PHP_MIXED) => throw new \Exception('Mixed type not supported'),
            ($typeName == TypeDetails::PHP_ARRAY) => throw new \Exception('Nested arrays not supported'),
            (in_array($typeName, TypeDetails::PHP_SCALAR_TYPES)) => $this->scalarType($typeName),
            default => $this->objectType($typeName),
        };
        return new TypeDetails(
            type: TypeDetails::PHP_ARRAY,
            nestedType: $nestedType
        );
    }

    /**
     * Create TypeDetails for object type
     *
     * @param string $typeName
     * @return TypeDetails
     */
    public function objectType(string $typeName) : TypeDetails {
        if ($this->classInfo->isEnum($typeName)) {
            return $this->enumType($typeName);
        }
        $instance = new TypeDetails(
            type: TypeDetails::PHP_OBJECT,
            class: $typeName
        );
        $instance->class = $typeName;
        return $instance;
    }

    /**
     * Create TypeDetails for enum type
     *
     * @param string $typeName
     * @return TypeDetails
     */
    public function enumType(string $typeName, string $enumType = null, array $enumValues = null) : TypeDetails {
        // enum specific
        if (!$this->classInfo->isBackedEnum($typeName)) {
            throw new \Exception('Enum must be backed by a string or int');
        }
        $backingType = $enumType ?? $this->classInfo->enumBackingType($typeName);
        if (!in_array($backingType, TypeDetails::PHP_ENUM_TYPES)) {
            throw new \Exception('Enum must be backed by a string or int');
        }
        return new TypeDetails(
            type: TypeDetails::PHP_ENUM,
            class: $typeName,
            enumType: $backingType,
            enumValues: $enumValues ?? $this->classInfo->enumValues($typeName)
        );
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Extract array type from type string
     */
    private function getArrayType(string $typeSpec) : string {
        if (substr($typeSpec, -2) !== '[]') {
            return $typeSpec;
        }
        return substr($typeSpec, 0, -2);
    }

    /**
     * Express Type[] type as array
     */
    private function normalizeIfArray(string $type) : string {
        if (substr($type, -2) === '[]') {
            return TypeDetails::PHP_ARRAY;
        }
        return $type;
    }

    /**
     * Express array type as <type>[]
     */
    private function arrayTypeString(Type $propertyInfo) : string {
        $collectionValueType = $propertyInfo->getCollectionValueTypes()[0];
        if ($collectionValueType === null) {
            throw new \Exception('Array type must have a collection value type specified');
        }
        $nestedType = $collectionValueType->getBuiltinType() ?? '';
        $nestedClass = $collectionValueType->getClassName() ?? '';
        return empty($nestedClass) ? "{$nestedType}[]" : "{$nestedClass}[]";
    }

    /**
     * Determine array type from array values
     */
    private function arrayTypeStringFromValues(array $array) : string
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
}

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
    /**
     * Create TypeDetails from type string
     *
     * @param string $anyType
     * @return \Cognesy\Instructor\Schema\Data\TypeDetails
     */
    public function fromTypeName(string $anyType) : TypeDetails {
        return match ($this->normalizeIfArray($anyType)) {
            'object' => throw new \Exception('Object type must have a class name'),
            'enum' => throw new \Exception('Enum type must have a class'),
            'array' => $this->arrayType($anyType),
            'int', 'string', 'bool', 'float' => $this->scalarType($anyType),
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
        return match($type) {
            'object', 'enum' => $this->fromTypeName($class),
            'array' => $this->fromTypeName($this->arrayTypeString($propertyInfo)), // express array type as <type>[]
            default => new TypeDetails($type),
        };
    }

    /**
     * Create TypeDetails from object instance
     *
     * @param object $instance
     * @return \Cognesy\Instructor\Schema\Data\TypeDetails
     */
    public function fromValue(mixed $anyVar) : TypeDetails {
        $type = gettype($anyVar);
        return match ($type) {
            'object' => $this->objectType(get_class($anyVar)),
            'array' => $this->arrayType($this->arrayTypeStringFromValues($anyVar)),
            'integer', 'string', 'boolean', 'double' => $this->scalarType($type),
            default => throw new \Exception('Unsupported type: '.$type),
        };
    }

    /**
     * Create TypeDetails for atom (scalar) type
     *
     * @param string $type
     * @return TypeDetails
     */
    protected function scalarType(string $type) : TypeDetails {
        return new TypeDetails($type, null, null, null, null);
    }

    /**
     * Create TypeDetails for array type
     *
     * @param string $typeSpec
     * @return \Cognesy\Instructor\Schema\Data\TypeDetails
     */
    protected function arrayType(string $typeSpec) : TypeDetails {
        $typeName = $this->getArrayType($typeSpec);
        $nestedType = match ($typeName) {
            'mixed' => throw new \Exception('Mixed type not supported'),
            'array' => throw new \Exception('Nested arrays not supported'),
            'int', 'string', 'bool', 'float' => $this->scalarType($typeName),
            default => $this->objectType($typeName),
        };
        return new TypeDetails(
            type: 'array',
            class: null,
            nestedType: $nestedType,
            enumType: null,
            enumValues: null);
    }

    /**
     * Create TypeDetails for object type
     *
     * @param string $typeName
     * @return TypeDetails
     */
    protected function objectType(string $typeName) : TypeDetails {
        if ((new ClassInfo)->isEnum($typeName)) {
            return $this->enumType($typeName);
        }
        $instance = new TypeDetails('object', $typeName, null, null, null);
        $instance->class = $typeName;
        return $instance;
    }

    /**
     * Create TypeDetails for enum type
     *
     * @param string $typeName
     * @return TypeDetails
     */
    protected function enumType(string $typeName) : TypeDetails {
        // enum specific
        if (!(new ClassInfo)->isBackedEnum($typeName)) {
            throw new \Exception('Enum must be backed by a string or int');
        }
        $backingType = (new ClassInfo)->enumBackingType($typeName);
        if (!in_array($backingType, ['int', 'string'])) {
            throw new \Exception('Enum must be backed by a string or int');
        }
        return new TypeDetails(
            type: 'enum',
            class: $typeName,
            nestedType: null,
            enumType: $backingType,
            enumValues: (new ClassInfo)->enumValues($typeName)
        );
    }

    /**
     * Extract array type from type string
     */
    private function getArrayType(string $typeSpec) : string {
        if (substr($typeSpec, -2) !== '[]') {
            throw new \Exception('Array type must end with []');
        }
        return substr($typeSpec, 0, -2);
    }

    /**
     * Express Type[] type as array
     */
    private function normalizeIfArray(string $type) : string {
        if (substr($type, -2) === '[]') {
            return 'array';
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
        $nestedType = gettype($array[0]);
        if (in_array($nestedType, ['int', 'string', 'bool', 'float'])) {
            return "{$nestedType}[]";
        }
        if ($nestedType === 'object') {
            $nestedClass = get_class($array[0]);
            return "{$nestedClass}[]";
        }
        throw new \Exception('Unsupported array element type: '.$nestedType);
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Schema\Factories\Traits\TypeDetailsFactory;

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Reflection\ClassInfo;

trait HandlesFactoryMethods
{
    /**
     * Create TypeDetails for atom (scalar) type
     *
     * @param string $type
     * @return TypeDetails
     */
    public function scalarType(string $type) : TypeDetails {
        if (empty($type)) {
            return $this->mixedType();
        }
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
     * @return TypeDetails
     */
    public function arrayType(string $typeSpec = '') : TypeDetails {
        //if ($this->isArrayShape($typeSpec)) {
        //    return $this->arrayShapeType($typeSpec);
        //}
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
     * @return TypeDetails
     */
    public function collectionType(string $typeSpec) : TypeDetails {
        $typeName = $this->getCollectionType($typeSpec);
        $nestedType = match (true) {
            ($typeName == TypeDetails::PHP_MIXED) => throw new \Exception('Mixed nested type not supported for collections - use array'),
            ($typeName == TypeDetails::PHP_ARRAY) => throw new \Exception('You have not specified collection element type'),
            (in_array($typeName, TypeDetails::PHP_SCALAR_TYPES)) => $this->scalarType($typeName),
            default => $this->objectType($typeName),
        };
        return new TypeDetails(
            type: TypeDetails::PHP_COLLECTION,
            class: null,
            nestedType: $nestedType,
            docString: $typeSpec,
        );
    }

    /**
     * Create TypeDetails for object type
     *
     * @param string $typeName
     * @return TypeDetails
     */
    public function objectType(string $typeName) : TypeDetails {
        if (ClassInfo::fromString($typeName)->isEnum()) {
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
    public function enumType(string $typeName, ?string $enumType = null, ?array $enumValues = null) : TypeDetails {
        $classInfo = ClassInfo::fromString($typeName);
        // enum specific
        if (!$classInfo->isBacked()) {
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

    public function optionType(array $values) : TypeDetails {
        return new TypeDetails(
            type: TypeDetails::PHP_STRING,
            class: null,
            enumValues: $values,
        );
    }

    /**
     * Create TypeDetails for mixed type
     *
     * @return TypeDetails
     */
    public function mixedType() : TypeDetails {
        return new TypeDetails(
            type: TypeDetails::PHP_MIXED,
            docString: 'mixed'
        );
    }

    // INTERNAL ///////////////////////////////////////////////////////////////////////////////

    /**
     * Create TypeDetails for array type
     *
     * @param string $typeSpec
     * @return TypeDetails
     */
    private function arrayShapeType(string $typeSpec) : TypeDetails {
        throw new \Exception('Array shape type not supported yet');
    }

    /**
     * Extract array type from type string
     */
    private function getCollectionType(string $typeSpec) : string {
        if (substr($typeSpec, -2) !== '[]') {
            return $typeSpec;
        }
        return substr($typeSpec, 0, -2);
    }

    private function isArrayShape(string $typeSpec) : bool {
        // TODO: not supported yet
        return false;
    }
}
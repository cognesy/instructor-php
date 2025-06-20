<?php

namespace Cognesy\Schema\Factories;

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\TypeString\TypeString;
use Cognesy\Utils\JsonSchema\JsonSchema;

/**
 * Factory for creating TypeDetails from type strings or PropertyInfo Type objects
 */
class TypeDetailsFactory
{
    use Traits\TypeDetailsFactory\HandlesFactoryMethods;

    // TYPE DETAILS RESOLUTION ////////////////////////////////////////////////////////////////

    public function fromPhpDocTypeString(string $typeSpec) : TypeDetails {
        if (empty($typeSpec)) {
            throw new \Exception('Type specification cannot be empty');
        }

        $typeString = TypeString::fromString($typeSpec);
        return match (true) {
            $typeString->isEmpty() => $this->mixedType(),
            $typeString->isScalar() => $this->scalarType($typeString->firstType()),
            $typeString->isCollection() => $this->collectionType($typeString->getItemType()),
            $typeString->isEnumObject() => $this->enumType($typeString->firstType()),
            $typeString->isObject() => $this->objectType($typeString->firstType()),
            $typeString->isArray() => $this->arrayType(),
            //($typeString->isOption()) => $this->optionType($typeString->types()),
            default => throw new \Exception('Unknown type: ' . $typeSpec),
        };
    }

    public function fromJsonSchema(JsonSchema $json) : TypeDetails {
        return match(true) {
            // object with no class -> exception
            $json->isObject() && !$json->hasObjectClass() => throw new \Exception('Object must have x-php-class field with the target class name'),
            // mixed
            $json->isMixed() => $this->mixedType(),
            // enum -> option
            $json->isString() && $json->hasEnumValues() && !$json->hasObjectClass() => $this->optionType($json->enumValues()),
            // enum -> enum
            $json->isString() && $json->hasEnumValues() && $json->hasObjectClass() => $this->enumType($json->objectClass(), TypeDetails::PHP_STRING, $json->enumValues()),
            // scalars
            $json->isScalar() => $this->scalarType(TypeDetails::toPhpType($json->type())),
            // object with class
            $json->isObject() => $this->objectType($json->objectClass()),
            // array with item types -> collection
            ($json->isCollection()) => $this->collectionType(match(true) {
                $json->itemSchema()->isScalar() => TypeDetails::toPhpType($json->itemSchema()->type()),
                $json->itemSchema()->isObject() => $json->itemSchema()->objectClass(),
                $json->itemSchema()->isEnum() => $json->itemSchema()->objectClass(),
                default => throw new \Exception('Collection item type must be scalar, object or enum'),
            }),
            // array with no item types -> array
            $json->isArray() => $this->arrayType(),
            // default to mixed
            default => $this->mixedType(),
        };
    }

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
            ($normalized === TypeDetails::PHP_OBJECT) => throw new \Exception('Object type must have a class name'),
            ($normalized === TypeDetails::PHP_ENUM) => throw new \Exception('Enum type must have a class'),
            ($normalized === TypeDetails::PHP_COLLECTION) => $this->collectionType($anyType),
            ($normalized === TypeDetails::PHP_ARRAY) => $this->arrayType(),
            ($normalized === TypeDetails::PHP_MIXED) => $this->mixedType(),
            (in_array($normalized, TypeDetails::PHP_SCALAR_TYPES)) => $this->scalarType($anyType),
            (class_exists($anyType)) => $this->objectType($anyType),
            default => $this->mixedType(),
        };
    }

    public function fromValue(mixed $anyVar) : TypeDetails {
        $type = TypeDetails::getType($anyVar);
        return match (true) {
            ($type == TypeDetails::PHP_OBJECT) => $this->objectType(get_class($anyVar)),
            ($type == TypeDetails::PHP_ARRAY && $this->allItemsShareType($anyVar)) => $this->collectionTypeStringFromValues($anyVar),
            ($type == TypeDetails::PHP_ARRAY) => $this->arrayType(),
            (in_array($type, TypeDetails::PHP_SCALAR_TYPES)) => $this->scalarType($type),
            default => $this->mixedType(),
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
    private function collectionTypeStringFromValues(array $array) : TypeDetails
    {
        if (empty($array)) {
            //throw new \Exception('Array is empty, cannot determine type of elements');
            return $this->arrayType();
        }
        $nestedType = TypeDetails::getType($array[0]);
        if (in_array($nestedType, TypeDetails::PHP_SCALAR_TYPES)) {
            return $this->collectionType("{$nestedType}[]");
        }
        if ($nestedType === TypeDetails::PHP_OBJECT) {
            $nestedClass = get_class($array[0]);
            return $this->collectionType("{$nestedClass}[]");
        }
        // return untyped array
        return $this->arrayType();
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
}

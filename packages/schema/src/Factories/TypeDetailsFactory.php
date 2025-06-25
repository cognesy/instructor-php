<?php

namespace Cognesy\Schema\Factories;

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\TypeString\TypeString;

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

        // TODO: we need more sophisticated parsing here with better support for unions
        $typeString = TypeString::fromString($typeSpec);
        return match (true) {
            $typeString->isMixed() => $this->mixedType(),
            $typeString->isScalar() => $this->scalarType($typeString->firstType()),
            $typeString->isEnumObject() => $this->enumType($typeString->firstType()),
            $typeString->isObject() => $this->objectType($typeString->firstType()),
            $typeString->isCollection() => $this->collectionType($typeString->itemType()),
            $typeString->isArray() => $this->arrayType(),
            default => throw new \Exception('Unknown type: ' . $typeSpec),
        };
    }

//    public function fromJsonSchema(JsonSchema $json) : TypeDetails {
//        return match(true) {
//            // object with no class -> exception
//            $json->isObject() && !$json->hasObjectClass() => throw new \Exception('Object must have x-php-class field with the target class name'),
//            // mixed
//            $json->isMixed() => $this->mixedType(),
//            // enum -> option
//            $json->isString() && $json->hasEnumValues() && !$json->hasObjectClass() => $this->optionType($json->enumValues()),
//            // enum -> enum
//            $json->isString() && $json->hasEnumValues() && $json->hasObjectClass() => $this->enumType($json->objectClass(), TypeDetails::PHP_STRING, $json->enumValues()),
//            // scalars
//            $json->isScalar() => $this->scalarType(TypeDetails::jsonToPhpType($json->type())),
//            // object with class
//            $json->isObject() => $this->objectType($json->objectClass()),
//            // array with item types -> collection
//            ($json->isCollection()) => $this->collectionType(match(true) {
//                $json->itemSchema()->isScalar() => TypeDetails::jsonToPhpType($json->itemSchema()->type()),
//                $json->itemSchema()->isObject() => $json->itemSchema()->objectClass(),
//                $json->itemSchema()->isEnum() => $json->itemSchema()->objectClass(),
//                default => throw new \Exception('Collection item type must be scalar, object or enum'),
//            }),
//            // array with no item types -> array
//            $json->isArray() => $this->arrayType(),
//            // default to mixed
//            default => $this->mixedType(),
//        };
//    }

    /**
     * Create TypeDetails from type string
     *
     * @param string $anyType
     * @return TypeDetails
     */
    public function fromTypeName(?string $anyType) : TypeDetails {
        if ($anyType === null) {
            return $this->mixedType();
        }

        $normalized = TypeString::fromString($anyType);
        return match (true) {
            ($normalized->isUntypedObject()) => throw new \Exception('Object type must have a class name'),
            ($normalized->isUntypedEnum()) => throw new \Exception('Enum type must have a class'),
            ($normalized->isCollection()) => $this->collectionType($anyType),
            ($normalized->isScalar()) => $this->scalarType($anyType),
            ($normalized->isEnumObject()) => $this->enumType($normalized->className()),
            ($normalized->isObject()) => $this->objectType($anyType),
            ($normalized->isArray()) => $this->arrayType(),
            ($normalized->isMixed()) => $this->mixedType(),
            default => throw new \Exception('Unsupported type: ' . $anyType),
        };
    }

    public function fromValue(mixed $anyVar) : TypeDetails {
        $typeName = TypeDetails::getPhpType($anyVar);
        $type = TypeString::fromString($typeName);
        return match (true) {
            $type->isScalar() => $this->scalarType($type),
            $type->isObject() => $this->objectType(get_class($anyVar)),
            $type->isEnumObject() => $this->enumType(get_class($anyVar)),
            is_array($anyVar) && empty($anyVar) => $this->arrayType(),
            $type->isArray() && $this->allItemsShareType($anyVar) => $this->collectionTypeStringFromValues($anyVar),
            $type->isArray() => $this->arrayType(),
            default => $this->mixedType(),
        };
    }

    // INTERNAL ///////////////////////////////////////////////////////////////////////////////

//    /**
//     * Express Type[] type as array
//     */
//    private function normalizeIfCollection(string $type) : string {
//        $typeString = TypeString::fromString($type);
//        return match(true) {
//            $typeString->isCollection() => $typeString->getItemType(),
//            $typeString->isArray() => TypeDetails::PHP_ARRAY,
//            default => $typeString->toString(),
//        };
//
//        //(substr($type, -2) === '[]') => TypeDetails::PHP_COLLECTION,
//        //($type === TypeDetails::PHP_ARRAY) => TypeDetails::PHP_ARRAY,
//        //(substr($type, 0, 5) === 'array') => TypeDetails::PHP_ARRAY,
//    }

    private function allItemsShareType(array $array) : bool {
        $type = TypeDetails::getPhpType($array[0]);
        foreach ($array as $item) {
            if ($item === null) {
                continue; // skip nulls
            }
            if (TypeDetails::getPhpType($item) !== $type) {
                return false;
            }
        }
        return true;
    }

    private function collectionTypeStringFromValues(array $array) : TypeDetails {
        if (empty($array)) {
            return $this->arrayType();
        }
        $nestedType = TypeDetails::getPhpType($array[0]);
        $type = TypeString::fromString($nestedType);
        if ($type->isScalar()) {
            return $this->collectionType("{$nestedType}[]");
        }
        if ($type->isObject() || $type->isEnumObject()) {
            $nestedClass = get_class($array[0]);
            return $this->collectionType("{$nestedClass}[]");
        }
        // return untyped array
        return $this->arrayType();
    }
}

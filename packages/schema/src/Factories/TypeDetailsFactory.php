<?php declare(strict_types=1);

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

        $typeString = TypeString::fromString($typeSpec);
        return $this->fromParsedTypeString($typeString, $typeSpec);
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
        return $this->fromParsedTypeString($normalized, $anyType);
    }

    public function fromValue(mixed $anyVar) : TypeDetails {
        if (is_object($anyVar)) {
            $className = get_class($anyVar) ?: throw new \Exception('Could not determine object class');
            if ($anyVar instanceof \BackedEnum) {
                return $this->enumType($className);
            }
            return $this->objectType($className);
        }
        if (is_array($anyVar) && empty($anyVar)) {
            return $this->arrayType();
        }
        if (is_array($anyVar)) {
            if ($this->allItemsShareType($anyVar)) {
                return $this->collectionTypeStringFromValues($anyVar);
            }
            return $this->arrayType();
        }

        $typeName = TypeDetails::getPhpType($anyVar) ?? TypeDetails::PHP_MIXED;
        $type = TypeString::fromString($typeName);
        return match (true) {
            $type->isScalar() => $this->scalarType($typeName),
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
        $type = null;
        foreach ($array as $item) {
            if ($item === null) {
                continue; // skip nulls
            }
            $itemType = TypeDetails::getPhpType($item);
            if ($itemType === TypeDetails::PHP_UNSUPPORTED) {
                return false;
            }
            if ($type === null) {
                $type = $itemType;
                continue;
            }
            if ($itemType !== $type) {
                return false;
            }
        }
        return $type !== null;
    }

    private function collectionTypeStringFromValues(array $array) : TypeDetails {
        if (empty($array)) {
            return $this->arrayType();
        }
        $sample = $this->firstCollectionSample($array);
        if ($sample === null) {
            return $this->arrayType();
        }
        $nestedType = TypeDetails::getPhpType($sample) ?? TypeDetails::PHP_MIXED;
        if ($nestedType === TypeDetails::PHP_UNSUPPORTED) {
            return $this->arrayType();
        }
        $type = TypeString::fromString($nestedType);
        return match (true) {
            $type->isScalar() => $this->collectionType("{$nestedType}[]"),
            is_object($sample) => $this->collectionType(get_class($sample) . '[]'),
            default => $this->arrayType(),
        };
    }

    private function firstCollectionSample(array $array): mixed {
        foreach ($array as $item) {
            if ($item !== null) {
                return $item;
            }
        }
        return null;
    }

    private function fromParsedTypeString(TypeString $typeString, string $sourceType): TypeDetails {
        if ($typeString->isUntypedObject()) {
            throw new \Exception('Object type must have a class name');
        }
        if ($typeString->isUntypedEnum()) {
            throw new \Exception('Enum type must have a class');
        }
        $unionType = $this->resolveUnionType($typeString, $sourceType);
        if ($unionType !== null) {
            return $unionType;
        }
        return match (true) {
            $typeString->isMixed() => $this->mixedType(),
            $typeString->isScalar() => $this->scalarType($typeString->firstType()),
            $typeString->isEnumObject() => (function() use ($typeString, $sourceType) {
                /** @var class-string $enumClass */
                $enumClass = $typeString->className() ?? throw new \Exception('Enum class name is required for type: ' . $sourceType);
                return $this->enumType($enumClass);
            })(),
            $typeString->isObject() => (function() use ($typeString, $sourceType) {
                /** @var class-string $objectClass */
                $objectClass = $typeString->className() ?? throw new \Exception('Object type must reference an existing class: ' . $sourceType);
                return $this->objectType($objectClass);
            })(),
            $typeString->isCollection() => $this->collectionType($typeString->itemType()),
            $typeString->isArray() => $this->arrayType(),
            default => throw new \Exception('Unsupported type: ' . $sourceType),
        };
    }

    private function resolveUnionType(TypeString $typeString, string $sourceType): ?TypeDetails {
        $nonNullTypes = array_values(array_filter(
            $typeString->types(),
            static fn(string $type): bool => $type !== TypeDetails::PHP_NULL,
        ));
        if (count($nonNullTypes) <= 1) {
            return null;
        }
        if ($this->allScalarTypes($nonNullTypes)) {
            return $this->scalarUnionType($nonNullTypes);
        }
        throw new \Exception('Union types with multiple non-null branches are not supported: ' . $sourceType);
    }

    private function allScalarTypes(array $types): bool {
        foreach ($types as $type) {
            if (!in_array($type, TypeDetails::PHP_SCALAR_TYPES, true)) {
                return false;
            }
        }
        return true;
    }

    private function scalarUnionType(array $types): TypeDetails {
        $normalized = array_values(array_unique($types));
        sort($normalized);
        if ($normalized === [TypeDetails::PHP_FLOAT, TypeDetails::PHP_INT]) {
            return $this->scalarType(TypeDetails::PHP_FLOAT);
        }
        return $this->mixedType();
    }
}

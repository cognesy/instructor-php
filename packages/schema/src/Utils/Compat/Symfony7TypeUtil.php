<?php

namespace Cognesy\Schema\Utils\Compat;

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\TypeString\TypeString;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\TypeIdentifier;

#[Deprecated('Not needed, temporarily kept until all usages are removed')]
class Symfony7TypeUtil
{
    public function fromTypeInfo(Type $typeInfo) : TypeDetails {
        if ($this->isCollection($typeInfo)) {
            $typeString = TypeString::fromString((string) $typeInfo);
            $collectionType = $typeString->toCollectionType();
            return TypeDetails::collection($collectionType);
        }
        if ($this->isArray($typeInfo)) {
            return TypeDetails::array();
        }
        if ($this->isScalar($typeInfo)) {
            $type = $this->typeInfoToScalar($typeInfo);
            return TypeDetails::scalar($type);
        }
        if ($this->isObject($typeInfo)) {
            $className = TypeString::fromString((string) $typeInfo)->toClassName();
            return TypeDetails::object($className);
        }
        return TypeDetails::mixed();
        //throw new \Exception('Unsupported type: '.$typeInfo);
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////

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
}
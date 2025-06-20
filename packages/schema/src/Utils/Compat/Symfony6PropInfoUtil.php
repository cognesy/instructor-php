<?php

namespace Cognesy\Schema\Utils\Compat;

use Cognesy\Schema\Data\TypeDetails;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\PropertyInfo\Type;

#[Deprecated('Not needed, temporarily kept until all usages are removed')]
class Symfony6PropInfoUtil
{
    public function fromPropertyInfo(Type $propertyInfo) : TypeDetails {
        $class = $propertyInfo->getClassName();
        $type = $propertyInfo->getBuiltinType();
        $collectionType = ($propertyInfo->getBuiltinType() === 'array') ? $this->collectionTypeString($propertyInfo) : '';
        return match(true) {
            (in_array($type, TypeDetails::PHP_OBJECT_TYPES)) => TypeDetails::fromTypeName($class),
            (in_array($type, TypeDetails::PHP_SCALAR_TYPES)) => TypeDetails::scalar($type),
            ($type === TypeDetails::PHP_ARRAY && $this->isCollectionNaive($collectionType)) => TypeDetails::collection($collectionType),
            ($type === TypeDetails::PHP_ARRAY) => TypeDetails::array(),
            ($class !== null) => TypeDetails::object($class),
            default => TypeDetails::mixed(),
            //default => throw new \Exception('Unsupported type: '.$type),
        };
    }

    private function isCollectionNaive(string $typeSpec) : bool {
        return match(true) {
            (substr($typeSpec, -2) === '[]') => true,
            default => false,
        };
    }
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
}
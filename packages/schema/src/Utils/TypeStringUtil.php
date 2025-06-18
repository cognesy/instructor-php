<?php

namespace Cognesy\Schema\Utils;

use Cognesy\Schema\Data\TypeDetails;

class TypeStringUtil
{
    public function toObjectTypeString(string $typeString) : string {
        if ($typeString === TypeDetails::PHP_OBJECT) {
            throw new \Exception('Object type must have a class name');
        }
        if ($typeString === TypeDetails::PHP_ENUM) {
            throw new \Exception('Enum type must have a class name');
        }
        $typeString = $this->removeNullable($typeString);
        if ($this->isUnionTypeString($typeString)) {
            $typeString = $this->fromUnionTypeString($typeString);
        }
        if (!class_exists($typeString)) {
            throw new \Exception("Object type class does not exist: `{$typeString}`");
        }
        return $typeString;
    }

    public function toCollectionTypeString(string $typeString) : string {
        $sourceTypeString = $typeString;
        $isNullable = false;
        if (substr($typeString, -2) === '[]') {
            return $typeString;
        }
        if (!substr($typeString, 0, 5) === 'array') {
            throw new \Exception('Unknown collection type string format: '.$sourceTypeString);
        }
        // array<int, string> => int, string
        $typeString = substr($typeString, 6, -1);
        // if there is a comma, take the second part (eg. int, string => string)
        if (strpos($typeString, ',') !== false) {
            $parts = explode(',', $typeString);
            $typeString = trim($parts[1]);
        }
        // if contains ? then remove it (eg. ?int => int)
        if (strpos($typeString, '?') !== false) {
            $isNullable = true;
            $typeString = str_replace('?', '', $typeString);
        }
        $typeString = $this->removeNullable($typeString);
        if ($this->isUnionTypeString($typeString)) {
            $typeString = $this->fromUnionTypeString($typeString);
        }
        // extract type from array<something> so we can return something[]
        if (in_array($typeString, TypeDetails::PHP_SCALAR_TYPES)) {
            return $isNullable ? "?{$typeString}[]" : "{$typeString}[]";
        }
        // fail on nested array (eg. array<int, array<string, int>>)
        if (strpos($typeString, 'array<') !== false) {
            throw new \Exception('Collection type cannot be an array of arrays: '.$sourceTypeString);
        }
        // fail on nested array
        if (substr($typeString, -2) === '[]') {
            throw new \Exception('Collection type cannot be an array of arrays: '.$sourceTypeString);
        }
        if ($typeString === TypeDetails::PHP_OBJECT) {
            throw new \Exception('Collection type must have a class name: '.$sourceTypeString);
        }
        if ($typeString === TypeDetails::PHP_ENUM) {
            throw new \Exception('Collection type must have a class name: '.$sourceTypeString);
        }
        if (!class_exists($typeString)) {
            throw new \Exception("Collection type class does not exist: `{$typeString}` for collection type string: `{$sourceTypeString}`");
        }
        return $isNullable ? "?{$typeString}[]" : "{$typeString}[]";
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////

    private function removeNullable(string $typeString) : string {
        // if union and has null then remove null item: int|null or null|int) => int
        if (strpos($typeString, 'null') !== false) {
            $isNullable = true;
            $parts = explode('|', $typeString);
            // remove null from parts
            $parts = array_filter($parts, fn($part) => trim($part) !== 'null');
            $typeString = trim(implode('|', $parts));
        }
        return $typeString;
    }

    private function isNullable(string $typeString) : bool {
        return strpos($typeString, 'null') !== false
            || strpos($typeString, '?') !== false;
    }

    private function isUnionTypeString(string $typeString) : bool {
        return strpos($typeString, '|') !== false;
    }

    private function fromUnionTypeString(string $typeString) : string {
        // if contains union type (eg. int|string), take the first part (eg. int)
        $parts = explode('|', $typeString);
        $typeString = trim($parts[0]);
        return $typeString;
    }
}
<?php

namespace Cognesy\Instructor\Utils;

class Arrays
{
    public static function unset(array $array, array|string $fields) : array {
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        foreach ($fields as $field) {
            unset($array[$field]);
        }
        return $array;
    }

    public static function toArray(mixed $value): array {
        if (is_array($value)) {
            return $value;
        }
        if (is_null($value)) {
            return [];
        }
        return [$value];
    }

    static public function isSubset(array $decodedKeys, array $propertyNames) {
        return count(array_diff($decodedKeys, $propertyNames)) === 0;
    }

    static public function removeTail(array $array, int $count) : array {
        if ($count < 1) {
            return $array;
        }
        return array_slice($array, 0, -$count);
    }

    static public function flatten(array $arrays, string $separator): string {
        return self::doFlatten($arrays, $separator);
    }

    private static function doFlatten(array $arrays, string $separator): string {
        $flat = '';
        foreach ($arrays as $item) {
            if (is_array($item)) {
                $flattenedItem = self::doFlatten($item, $separator);
                if ($flattenedItem !== '') {
                    $flat .= $flattenedItem . $separator;
                }
            } else {
                $trimmedItem = trim((string) $item);
                if ($trimmedItem !== '') {
                    $flat .= $trimmedItem . $separator;
                }
            }
        }
        return rtrim($flat, $separator);
    }
}
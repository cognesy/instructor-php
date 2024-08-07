<?php

namespace Cognesy\Instructor\Utils;

use WeakMap;

class Arrays
{
    public static function mergeNull(?array $array1, ?array $array2): ?array {
        return match(true) {
            is_null($array1) && is_null($array2) => null,
            is_null($array1) => $array2,
            is_null($array2) => $array1,
            default => array_merge($array1, $array2),
        };
    }

    public static function unset(array $array, array|string $fields) : array {
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        foreach ($fields as $field) {
            unset($array[$field]);
        }
        return $array;
    }

    public static function asArray(mixed $value): array {
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

    static public function flatten(array $arrays, string $separator = ''): string {
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

    static public function fromAny(mixed $anyValue): array {
        $visited = new WeakMap();
        $toArray = function($x) use(&$toArray, &$visited) {
            $markAndMap = function($x) use(&$toArray, &$visited) {
                $visited[$x] = true; // mark as visited, so we handle circular references
                return array_map($toArray, get_object_vars($x));
            };
            return match(true) {
                is_scalar($x) || is_null($x) => $x,
                is_object($x) && isset($visited[$x]) => 'ref-cycle: ' . get_class($x),
                is_object($x) => $markAndMap($x),
                default => array_map($toArray, $x),
            };
        };
        return $toArray($anyValue);
    }

    static public function removeRecursively(array $array, array $keys): array {
        $remove = function($array, $keys) use(&$remove) {
            foreach ($array as $key => $value) {
                if (in_array($key, $keys)) {
                    unset($array[$key]);
                } elseif (is_array($value)) {
                    $array[$key] = $remove($value, $keys);
                }
            }
            return $array;
        };
        return $remove($array, $keys);
    }
}

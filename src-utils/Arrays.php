<?php

namespace Cognesy\Utils;

use WeakMap;

/**
 * Utility functions for working with arrays.
 */
class Arrays
{
    /**
     * Merges two arrays, handling null values.
     * @param array|null $array1
     * @param array|null $array2
     * @return array|null
     */
    public static function mergeNull(?array $array1, ?array $array2): ?array {
        return match(true) {
            is_null($array1) && is_null($array2) => null,
            is_null($array1) => $array2,
            is_null($array2) => $array1,
            default => array_merge($array1, $array2),
        };
    }

    /**
     * Merges two arrays, handling null values.
     * @param array|null $array1
     * @param array|null $array2
     * @return array|null
     */
    public static function unset(array $array, array|string $fields) : array {
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        foreach ($fields as $field) {
            unset($array[$field]);
        }
        return $array;
    }

    /**
     * Converts a value to an array.
     * @param mixed $value
     * @return array
     */
    public static function asArray(mixed $value): array {
        if (is_array($value)) {
            return $value;
        }
        if (is_null($value)) {
            return [];
        }
        return [$value];
    }

    /**
     * Checks if an array is a subset of another array.
     * @param array $decodedKeys
     * @param array $propertyNames
     * @return bool
     */
    static public function isSubset(array $decodedKeys, array $propertyNames) {
        return count(array_diff($decodedKeys, $propertyNames)) === 0;
    }

    /**
     * Removes the last N elements from an array.
     * @param array $array
     * @param int $count
     * @return array
     */
    static public function removeTail(array $array, int $count) : array {
        if ($count < 1) {
            return $array;
        }
        return array_slice($array, 0, -$count);
    }

    /**
     * Flattens an array of arrays into a single string.
     * @param array $arrays
     * @param string $separator
     * @return string
     */
    static public function flatten(array $arrays, string $separator = ''): string {
        return self::doFlatten($arrays, $separator);
    }

    /**
     * Maps an array using a callback.
     * @param array $array
     * @param callable $callback
     * @return array
     */
    static public function map(array $array, callable $callback): array {
        $target = [];
        foreach ($array as $key => $value) {
            $target[$key] = $callback($value, $key);
        }
        return $target;
    }

    /**
     * Filters an array using a callback.
     * @param array $array
     * @param callable $callback
     * @return array
     */
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

    /**
     * Filters an array using a callback.
     * @param array $array
     * @param callable $callback
     * @return array
     */
    static public function removeRecursively(array $array, array $keys): array {
        if (empty($array) || empty($keys)) {
            return $array;
        }
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

    /**
     * Filters an array using a callback.
     * @param array $array
     * @param callable $callback
     * @return array
     */
    static public function toBullets(array $array): string {
        return implode("\n", array_map(fn($c) => " - {$c}\n", $array));
    }

    // INTERNAL ///////////////////////////////////////////////////////

    /**
     * Flattens an array of arrays into a single string.
     * @param array $arrays
     * @param string $separator
     * @return string
     */
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

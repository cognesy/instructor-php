<?php declare(strict_types=1);

namespace Cognesy\Utils;

use Generator;
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
     * Unsets specified fields from an array.
     * @param array $array
     * @param array|string $fields
     * @return array
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
     *
     * @param array $compareTo
     * @param array $compared
     * @return bool
     */
    static public function isSubset(array $compareTo, array $compared) {
        return count(array_diff($compareTo, $compared)) === 0;
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
     * Maps an array using a callback.
     * @param array $array
     * @param callable(mixed, array-key): mixed $callback
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
     * Converts any value to array representation.
     * @param mixed $anyValue
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
                is_scalar($x) || is_null($x) => [$x],
                is_object($x) && isset($visited[$x]) => ['ref-cycle: ' . get_class($x)],
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
    static public function removeRecursively(array $array, array $keys, array $skip = []): array {
        if (empty($array) || empty($keys)) {
            return $array;
        }
        $remove = function($array, $keys, $skip) use(&$remove) {
            foreach ($array as $key => $value) {
                if (in_array($key, $keys)) {
                    unset($array[$key]);
                } elseif (is_array($value)) {
                    if (!in_array($key, $skip)) {
                        $array[$key] = $remove($value, $keys, $skip);
                    }
                }
            }
            return $array;
        };
        return $remove($array, $keys, $skip);
    }

    /**
     * Converts an array to a bulleted list string.
     * @param array $array
     * @return string
     */
    static public function toBullets(array $array): string {
        return implode("\n", array_map(fn($c) => " - {$c}\n", $array));
    }

    /**
     * Flattens an array of arrays into a single string.
     * @param array $arrays
     * @param string $separator
     * @return string
     */
    static public function flattenToString(array $arrays, string $separator = ''): string {
        return self::doFlattenToString($arrays, $separator);
    }

    // turn array of arrays with key = string, value = mixed/object into a single array
    static public function flatten(array $arrays): array {
        return iterator_to_array(self::doFlatten($arrays), false);
    }

    // INTERNAL ///////////////////////////////////////////////////////

    static private function doFlatten(mixed $maybeArray) : Generator {
        if (is_array($maybeArray)) {
            foreach ($maybeArray as $item) {
                yield from self::doFlatten($item);
            }
        } else {
            yield $maybeArray;
        }
    }

    /**
     * Flattens an array of arrays into a single string.
     * @param array $arrays
     * @param string $separator
     * @return string
     */
    private static function doFlattenToString(array $arrays, string $separator): string {
        $flat = '';
        foreach ($arrays as $item) {
            if (is_array($item)) {
                $flattenedItem = self::doFlattenToString($item, $separator);
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

    public static function valuesMatch(array $a, array $b) : bool {
        if (count($a) !== count($b)) {
            return false;
        }
        // we want to check if all values in $a are present in $b
        // by making sure that intersection of both arrays is equal to $a
        $intersection = array_intersect($a, $b);
        if (count($intersection) !== count($a)) {
            return false;
        }
        // we also want to check if all values in $b are present in $a
        $intersection = array_intersect($b, $a);
        if (count($intersection) !== count($b)) {
            return false;
        }
        // if we reach here, it means that both arrays have the same values
        return true;
    }

    public static function hasOnlyStrings(array $content) : bool {
        return count($content) > 0 && array_reduce(
            $content,
            fn(bool $carry, $item) => $carry && is_string($item),
            true
        );
    }
}

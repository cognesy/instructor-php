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
     * Efficiently merge many arrays produced in a loop.
     * Avoids calling array_merge inside the loop by doing a single merge.
     *
     * Example:
     *  $chunks = [];
     *  foreach ($sources as $s) { $chunks[] = $s->getOptions(); }
     *  $merged = Arrays::mergeMany($chunks);
     *
     * Semantics match PHP's array_merge: numeric keys are reindexed and later
     * values overwrite earlier ones for string keys.
     *
     * @param iterable<array<int|string, mixed>> $arrays
     * @return array<int|string, mixed>
     */
    public static function mergeMany(iterable $arrays): array {
        $parts = [];
        foreach ($arrays as $arr) {
            if (!is_array($arr) || $arr === []) {
                continue;
            }
            $parts[] = $arr;
        }
        return self::mergeParts($parts);
    }

    /**
     * Map items to arrays and merge them efficiently.
     * Useful when each item must be transformed into an array before merging.
     *
     * Example:
     *  $errors = Arrays::mergeOver($attempts, fn($a) => $a->hasErrors() ? $a->errors() : []);
     *
     * @param iterable<mixed> $items
     * @param callable(mixed, array-key): array $toArray maps item to array
     * @return array<int|string, mixed>
     */
    public static function mergeOver(iterable $items, callable $toArray): array {
        $parts = [];
        foreach ($items as $key => $item) {
            $arr = $toArray($item, $key);
            if ($arr === []) {
                continue;
            }
            $parts[] = $arr;
        }
        return self::mergeParts($parts);
    }

    /**
     * Single merge point for mergeMany/mergeOver.
     *
     * A one-element fast path must still go through array_merge: returning $parts[0]
     * verbatim would preserve numeric keys, while the documented array_merge semantics
     * reindex them, so mergeMany([[5 => 'a']]) disagreed with array_merge([5 => 'a']).
     *
     * @param list<array<int|string, mixed>> $parts
     * @return array<int|string, mixed>
     */
    private static function mergeParts(array $parts): array {
        return $parts === [] ? [] : array_merge(...$parts);
    }

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
    static public function isSubset(array $compareTo, array $compared) : bool {
        return array_diff($compareTo, $compared) === [];
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
        // $onPath tracks the objects on the current descent, not every object ever
        // seen. Marking permanently made any object referenced twice look like a
        // cycle, so a plain diamond ($root->a and $root->b sharing one child)
        // reported 'ref-cycle' for the second reference even though nothing recursed.
        $onPath = new WeakMap();
        $toArray = function($x) use(&$toArray, $onPath) {
            $descend = function($x) use(&$toArray, $onPath) {
                $onPath[$x] = true;
                try {
                    return array_map($toArray, get_object_vars($x));
                } finally {
                    unset($onPath[$x]);
                }
            };
            return match(true) {
                is_scalar($x) || is_null($x) => [$x],
                is_object($x) && isset($onPath[$x]) => ['ref-cycle: ' . get_class($x)],
                is_object($x) => $descend($x),
                default => array_map($toArray, $x),
            };
        };
        return $toArray($anyValue);
    }

    /**
     * Recursively removes the given keys from an array.
     *
     * @param array $array The array to prune.
     * @param array $keys Keys to remove at every depth.
     * @param array $skip Keys whose sub-arrays are left untouched.
     * @return array The pruned array.
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
     * Converts an array to a bulleted list string, one bullet per line.
     *
     * @param array $array
     * @return string
     */
    static public function toBullets(array $array): string {
        // Each item used to carry its own trailing "\n" on top of the implode glue,
        // so every list came out double-spaced with a stray newline at the end.
        return implode("\n", array_map(static fn($c) => " - {$c}", $array));
    }

    /**
     * Flattens an array of arrays into a single string.
     * @param array $arrays
     * @param string $separator
     * @return string
     */
    static public function flattenToString(array $arrays, string $separator = ''): string {
        return implode($separator, self::flattenToStringParts($arrays));
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
     * Collects every non-empty leaf of a nested array, depth-first, as trimmed strings.
     *
     * Joining is left to the caller via implode. The previous implementation appended
     * the separator after each part and stripped it with rtrim($flat, $separator),
     * but rtrim treats its second argument as a *character set*, not a suffix: any
     * trailing character that merely appeared in the separator was eaten. With
     * separator 'ab', ['ab','ba'] collapsed to '' - the whole result was consumed.
     *
     * @param array $arrays
     * @return list<string>
     */
    private static function flattenToStringParts(array $arrays): array {
        $parts = [];
        foreach ($arrays as $item) {
            if (is_array($item)) {
                foreach (self::flattenToStringParts($item) as $nested) {
                    $parts[] = $nested;
                }
                continue;
            }
            $trimmedItem = trim((string) $item);
            if ($trimmedItem !== '') {
                $parts[] = $trimmedItem;
            }
        }
        return $parts;
    }

    /**
     * Tests equal length plus mutual containment of values, compared loosely
     * (array_intersect casts to string).
     *
     * This is set equality only when at least one side is duplicate-free, which is
     * the case for every caller today. With duplicates on both sides it is neither
     * set nor multiset equality: [1, 1, 2] and [1, 2, 2] match, while [1, 1, 2] and
     * [1, 2] - equal as sets - do not. Use array_unique() first if you want plain
     * set equality regardless of duplicates.
     */
    public static function valuesMatch(array $a, array $b) : bool {
        return count($a) === count($b)
            && array_intersect($a, $b) === $a
            && array_intersect($b, $a) === $b;
    }

    public static function hasOnlyStrings(array $content) : bool {
        return count($content) > 0 && array_reduce(
            $content,
            fn(bool $carry, $item) => $carry && is_string($item),
            true
        );
    }
}

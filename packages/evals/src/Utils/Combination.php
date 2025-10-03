<?php declare(strict_types=1);

namespace Cognesy\Evals\Utils;

use ArrayIterator;
use Cognesy\Evals\Contracts\CanMapValues;
use Generator;
use InvalidArgumentException;
use Iterator;

/**
 * Class responsible for generating combinations of multiple iterables.
 */
class Combination
{
    /**
     * Generates all possible combinations of the provided sources and maps them to instances of the mapping class.
     *
     * @template T of CanMapValues
     *
     * @param class-string<T> $mapping The fully qualified class name that implements CanMapValues.
     * @param array<string, iterable> $sources Associative array mapping keys to their respective iterables.
     *
     * @return Generator<int, T> Yields instances of the mapping class for each combination.
     *
     * @throws InvalidArgumentException If any key in order is missing from sources.
     */
    public static function generator(
        string $mapping,
        array $sources
    ): Generator {
        $order = array_keys($sources);
        // Ensure all keys in order exist in sources
        foreach ($order as $key) {
            if (!array_key_exists($key, $sources)) {
                throw new InvalidArgumentException("Source for key '{$key}' not provided.");
            }
        }

        // Initialize iterators for each key in the specified order
        $iterators = [];
        foreach ($order as $key) {
            $iterators[$key] = self::getIterator($sources[$key]);
        }

        // Start the recursive generation of combinations
        yield from self::generateCombinations($mapping, $order, $iterators, []);
    }

    /**
     * Recursively generates combinations of values.
     *
     * @template T of CanMapValues
     *
     * @param class-string<T> $mapping
     * @param string[] $keys
     * @param array<string, Iterator> $iterators
     * @param array<string, mixed> $currentCombination
     *
     * @return Generator<int, T>
     */
    private static function generateCombinations(
        string $mapping,
        array $keys,
        array $iterators,
        array $currentCombination
    ): Generator {
        $key = array_shift($keys);

        if ($key === null) {
            // Base case: all keys have been processed
            yield $mapping::map($currentCombination);
            return;
        }

        foreach ($iterators[$key] as $value) {
            $newCombination = $currentCombination;
            $newCombination[$key] = $value;

            // Recursively generate combinations for the remaining keys
            yield from self::generateCombinations($mapping, $keys, $iterators, $newCombination);
        }
    }

    /**
     * Converts an iterable into an Iterator.
     *
     * @param iterable $iterable
     * @return Iterator
     */
    private static function getIterator(iterable $iterable): Iterator
    {
        if ($iterable instanceof Iterator) {
            return $iterable;
        }

        return new ArrayIterator(is_array($iterable) ? $iterable : iterator_to_array($iterable));
    }
}

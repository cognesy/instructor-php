<?php declare(strict_types=1);

namespace Cognesy\Evals\Utils;

use Adbar\Dot;

class CompareNestedArrays
{
    /**
     * @var array List of keys to ignore during comparison.
     */
    private array $ignoreKeys;

    /**
     * Constructor.
     *
     * @param array $ignoreKeys Keys to ignore in dot notation.
     */
    public function __construct(array $ignoreKeys = [])
    {
        $this->ignoreKeys = $ignoreKeys;
    }

    /**
     * Compares two arrays and returns the differences.
     *
     * @param array $expected The expected array.
     * @param array $actual The actual array.
     * @return array The differences with dot notation keys.
     */
    public function compare(array $expected, array $actual): array
    {
        // Flatten both arrays using Dot notation
        $dotExpected = new Dot($expected);
        $dotActual = new Dot($actual);

        $flattenedExpected = $dotExpected->flatten();
        $flattenedActual = $dotActual->flatten();

        // Remove ignored keys
        foreach ($this->ignoreKeys as $ignoreKey) {
            unset($flattenedExpected[$ignoreKey], $flattenedActual[$ignoreKey]);
        }

        // Get all unique keys from both arrays
        $allKeys = array_unique(array_merge(array_keys($flattenedExpected), array_keys($flattenedActual)));

        $differences = [];

        foreach ($allKeys as $key) {
            $existsInExpected = array_key_exists($key, $flattenedExpected);
            $existsInActual = array_key_exists($key, $flattenedActual);

            $expectedValue = $existsInExpected ? $flattenedExpected[$key] : null;
            $actualValue = $existsInActual ? $flattenedActual[$key] : null;

            // Determine if there is a difference
            if (!$existsInExpected) {
                // Key exists only in actual
                $differences[$key] = [
                    'expected' => null,
                    'actual' => $actualValue,
                ];
            } elseif (!$existsInActual) {
                // Key exists only in expected
                $differences[$key] = [
                    'expected' => $expectedValue,
                    'actual' => null,
                ];
            } elseif (!$this->valuesAreEqual($expectedValue, $actualValue)) {
                // Key exists in both but values differ
                $differences[$key] = [
                    'expected' => $expectedValue,
                    'actual' => $actualValue,
                ];
            }
            // If values are the same, do nothing
        }

        return $differences;
    }

    /**
     * Custom equality check for values.
     *
     * @param mixed $expected
     * @param mixed $actual
     * @return bool
     */
    private function valuesAreEqual($expected, $actual): bool
    {
        // Add custom comparison logic if needed
        // For example, handle floating-point precision
        if (is_float($expected) && is_float($actual)) {
            return abs($expected - $actual) < 0.0001; // Tolerance
        }

        // For other types, use strict comparison
        return $expected === $actual;
    }
}
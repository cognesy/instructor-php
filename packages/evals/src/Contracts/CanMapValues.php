<?php

namespace Cognesy\Evals\Contracts;

/**
 * Interface for mapping a set of values to an object.
 *
 * @template T
 */
interface CanMapValues
{
    /**
     * Maps an associative array of values to an instance of T.
     *
     * @param array<string, mixed> $values
     * @return T
     */
    public static function map(array $values);
}

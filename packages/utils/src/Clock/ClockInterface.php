<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Contracts;

use Cognesy\Pipeline\Support\Duration;

/**
 * Interface for time providers.
 */
interface ClockInterface
{
    /**
     * Get the current time in microseconds since Unix epoch.
     */
    public function now(): int;

    /**
     * Sleep for the specified duration.
     */
    public function sleep(Duration $duration): void;

    /**
     * Get elapsed time since a starting point.
     */
    public function elapsed(int $startTime): Duration;

    /**
     * Check if a timeout has been reached.
     */
    public function hasTimedOut(int $startTime, Duration $timeout): bool;
}
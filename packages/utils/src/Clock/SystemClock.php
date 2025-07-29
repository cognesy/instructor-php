<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Support;

use Cognesy\Pipeline\Contracts\ClockInterface;

/**
 * System clock implementation using real time.
 */
class SystemClock implements ClockInterface
{
    public function now(): int
    {
        return (int) (microtime(true) * 1_000_000);
    }

    public function sleep(Duration $duration): void
    {
        usleep($duration->toMicroseconds());
    }

    public function elapsed(int $startTime): Duration
    {
        return Duration::microseconds($this->now() - $startTime);
    }

    public function hasTimedOut(int $startTime, Duration $timeout): bool
    {
        return $this->elapsed($startTime)->isGreaterThan($timeout);
    }
}
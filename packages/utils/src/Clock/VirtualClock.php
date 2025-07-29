<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Support;

use Cognesy\Pipeline\Contracts\ClockInterface;

/**
 * Virtual clock for testing with manual time control.
 */
class VirtualClock implements ClockInterface
{
    private int $currentTime;

    public function __construct(int $startTime = 0)
    {
        $this->currentTime = $startTime;
    }

    /**
     * Create a virtual clock starting at the current system time.
     */
    public static function fromSystemTime(): self
    {
        return new self((int) (microtime(true) * 1_000_000));
    }

    public function now(): int
    {
        return $this->currentTime;
    }

    public function sleep(Duration $duration): void
    {
        $this->advance($duration);
    }

    public function elapsed(int $startTime): Duration
    {
        return Duration::microseconds($this->currentTime - $startTime);
    }

    public function hasTimedOut(int $startTime, Duration $timeout): bool
    {
        return $this->elapsed($startTime)->isGreaterThan($timeout);
    }

    /**
     * Manually advance the clock by the specified duration.
     */
    public function advance(Duration $duration): void
    {
        $this->currentTime += $duration->toMicroseconds();
    }

    /**
     * Set the clock to a specific time.
     */
    public function setTime(int $microseconds): void
    {
        $this->currentTime = $microseconds;
    }

    /**
     * Reset the clock to zero.
     */
    public function reset(): void
    {
        $this->currentTime = 0;
    }
}
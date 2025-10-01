<?php declare(strict_types=1);

namespace Cognesy\Utils\Time;

use DateTimeImmutable;

/**
 * Virtual clock for deterministic testing.
 * Allows manual control of time for predictable test scenarios.
 */
final class VirtualClock implements ClockInterface
{
    private DateTimeImmutable $currentTime;

    public function __construct(?DateTimeImmutable $startTime = null)
    {
        $this->currentTime = $startTime ?? new DateTimeImmutable();
    }

    public function now(): DateTimeImmutable
    {
        return $this->currentTime;
    }

    /**
     * Set the current time to a specific moment.
     */
    public function setTime(DateTimeImmutable $time): self
    {
        $this->currentTime = $time;
        return $this;
    }

    /**
     * Advance the clock by a given number of seconds.
     */
    public function advance(int $seconds): self
    {
        $this->currentTime = $this->currentTime->modify("+{$seconds} seconds");
        return $this;
    }

    /**
     * Rewind the clock by a given number of seconds.
     */
    public function rewind(int $seconds): self
    {
        $this->currentTime = $this->currentTime->modify("-{$seconds} seconds");
        return $this;
    }

    /**
     * Advance the clock by a given interval string (e.g., '+1 hour', '+2 days').
     */
    public function advanceBy(string $interval): self
    {
        $this->currentTime = $this->currentTime->modify($interval);
        return $this;
    }

    /**
     * Reset the clock to a specific timestamp.
     */
    public function reset(int $timestamp): self
    {
        $this->currentTime = (new DateTimeImmutable())->setTimestamp($timestamp);
        return $this;
    }

    /**
     * Create a VirtualClock frozen at a specific time.
     */
    public static function at(string $time): self
    {
        return new self(new DateTimeImmutable($time));
    }

    /**
     * Create a VirtualClock frozen at Unix epoch (1970-01-01 00:00:00 UTC).
     */
    public static function atEpoch(): self
    {
        return new self(new DateTimeImmutable('@0'));
    }

    /**
     * Get the current timestamp.
     */
    public function timestamp(): int
    {
        return $this->currentTime->getTimestamp();
    }
}
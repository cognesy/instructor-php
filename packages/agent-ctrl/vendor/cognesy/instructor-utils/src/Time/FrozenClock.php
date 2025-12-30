<?php declare(strict_types=1);

namespace Cognesy\Utils\Time;

use DateTimeImmutable;

/**
 * Frozen clock that always returns the same time.
 * Useful for tests that need consistent time without any advancement.
 */
final readonly class FrozenClock implements ClockInterface
{
    private DateTimeImmutable $frozenTime;

    public function __construct(?DateTimeImmutable $time = null)
    {
        $this->frozenTime = $time ?? new DateTimeImmutable();
    }

    #[\Override]
    public function now(): DateTimeImmutable
    {
        return $this->frozenTime;
    }

    /**
     * Create a FrozenClock at a specific time.
     */
    public static function at(string $time): self
    {
        return new self(new DateTimeImmutable($time));
    }

    /**
     * Create a FrozenClock at Unix epoch (1970-01-01 00:00:00 UTC).
     */
    public static function atEpoch(): self
    {
        return new self(new DateTimeImmutable('@0'));
    }

    /**
     * Create a FrozenClock at the current time.
     */
    public static function create(): self
    {
        return new self();
    }
}
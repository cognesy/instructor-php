<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Support;

/**
 * Immutable duration value object for time measurements.
 */
readonly class Duration
{
    private function __construct(
        private int $microseconds,
    ) {}

    /**
     * Create duration from microseconds.
     */
    public static function microseconds(int $microseconds): self
    {
        return new self($microseconds);
    }

    /**
     * Create duration from milliseconds.
     */
    public static function milliseconds(int|float $milliseconds): self
    {
        return new self((int) ($milliseconds * 1_000));
    }

    /**
     * Create duration from seconds.
     */
    public static function seconds(int|float $seconds): self
    {
        return new self((int) ($seconds * 1_000_000));
    }

    /**
     * Create duration from minutes.
     */
    public static function minutes(int|float $minutes): self
    {
        return new self((int) ($minutes * 60 * 1_000_000));
    }

    /**
     * Create duration from hours.
     */
    public static function hours(int|float $hours): self
    {
        return new self((int) ($hours * 60 * 60 * 1_000_000));
    }

    /**
     * Get duration in microseconds.
     */
    public function toMicroseconds(): int
    {
        return $this->microseconds;
    }

    /**
     * Get duration in milliseconds.
     */
    public function toMilliseconds(): float
    {
        return $this->microseconds / 1_000;
    }

    /**
     * Get duration in seconds.
     */
    public function toSeconds(): float
    {
        return $this->microseconds / 1_000_000;
    }

    /**
     * Get duration in minutes.
     */
    public function toMinutes(): float
    {
        return $this->microseconds / (60 * 1_000_000);
    }

    /**
     * Get duration in hours.
     */
    public function toHours(): float
    {
        return $this->microseconds / (60 * 60 * 1_000_000);
    }

    /**
     * Add another duration.
     */
    public function plus(Duration $other): self
    {
        return new self($this->microseconds + $other->microseconds);
    }

    /**
     * Subtract another duration.
     */
    public function minus(Duration $other): self
    {
        return new self(max(0, $this->microseconds - $other->microseconds));
    }

    /**
     * Multiply by a factor.
     */
    public function multiply(int|float $factor): self
    {
        return new self((int) ($this->microseconds * $factor));
    }

    /**
     * Check if this duration is greater than another.
     */
    public function isGreaterThan(Duration $other): bool
    {
        return $this->microseconds > $other->microseconds;
    }

    /**
     * Check if this duration is less than another.
     */
    public function isLessThan(Duration $other): bool
    {
        return $this->microseconds < $other->microseconds;
    }

    /**
     * Check if this duration equals another.
     */
    public function equals(Duration $other): bool
    {
        return $this->microseconds === $other->microseconds;
    }

    /**
     * Check if duration is zero.
     */
    public function isZero(): bool
    {
        return $this->microseconds === 0;
    }

    /**
     * Get string representation.
     */
    public function toString(): string
    {
        if ($this->microseconds < 1_000) {
            return "{$this->microseconds}μs";
        }
        if ($this->microseconds < 1_000_000) {
            return number_format($this->toMilliseconds(), 2) . 'ms';
        }
        if ($this->microseconds < 60 * 1_000_000) {
            return number_format($this->toSeconds(), 2) . 's';
        }
        if ($this->microseconds < 60 * 60 * 1_000_000) {
            return number_format($this->toMinutes(), 2) . 'm';
        }
        return number_format($this->toHours(), 2) . 'h';
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
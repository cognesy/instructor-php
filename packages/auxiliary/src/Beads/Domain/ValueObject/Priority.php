<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Priority Value Object
 *
 * Represents task priority with semantic meaning (0=highest, 4=lowest)
 *
 * @psalm-immutable
 */
final readonly class Priority
{
    /**
     * @param  int  $value  Priority value (0-4)
     * @param  string  $label  Human-readable label
     *
     * @throws InvalidArgumentException If value is out of range
     */
    private function __construct(
        public int $value,
        public string $label,
    ) {
        if ($value < 0 || $value > 4) {
            throw new InvalidArgumentException(
                "Priority must be 0-4, got: {$value}"
            );
        }
    }

    /**
     * Create critical priority (0)
     */
    public static function critical(): self
    {
        return new self(0, 'Critical');
    }

    /**
     * Create high priority (1)
     */
    public static function high(): self
    {
        return new self(1, 'High');
    }

    /**
     * Create medium priority (2)
     */
    public static function medium(): self
    {
        return new self(2, 'Medium');
    }

    /**
     * Create low priority (3)
     */
    public static function low(): self
    {
        return new self(3, 'Low');
    }

    /**
     * Create backlog priority (4)
     */
    public static function backlog(): self
    {
        return new self(4, 'Backlog');
    }

    /**
     * Create from integer value
     *
     * @throws InvalidArgumentException If value is out of range
     */
    public static function fromInt(int $value): self
    {
        return match ($value) {
            0 => self::critical(),
            1 => self::high(),
            2 => self::medium(),
            3 => self::low(),
            4 => self::backlog(),
            default => throw new InvalidArgumentException(
                "Invalid priority value: {$value}. Must be 0-4."
            ),
        };
    }

    /**
     * Check if this is critical priority
     */
    public function isCritical(): bool
    {
        return $this->value === 0;
    }

    /**
     * Check if this is higher priority than another
     */
    public function isHigherThan(Priority $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Check if this is lower priority than another
     */
    public function isLowerThan(Priority $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Check equality with another Priority
     */
    public function equals(Priority $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return "{$this->label} ({$this->value})";
    }
}

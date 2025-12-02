<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Task Identifier Value Object
 *
 * Represents a unique task identifier in the format: {project}-{hash}
 * Examples: partnerspot-heow, bd-abc123
 *
 * @psalm-immutable
 */
final readonly class TaskId
{
    /**
     * @param  string  $value  The task ID in format: {project}-{hash}
     *
     * @throws InvalidArgumentException If the format is invalid
     */
    public function __construct(
        public string $value,
    ) {
        // Pattern: {project}-{hash} where hash is 4+ alphanumeric chars
        // Examples: partnerspot-heow, bd-abc123, myproject-xyz9
        if (! preg_match('/^[a-z0-9]+-[a-z0-9]{4,}$/i', $value)) {
            throw new InvalidArgumentException(
                "Invalid task ID format: {$value}. Expected: project-hash"
            );
        }
    }

    /**
     * Create TaskId from string
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Get project prefix (before first hyphen)
     */
    public function prefix(): string
    {
        return explode('-', $this->value, 2)[0];
    }

    /**
     * Get hash portion (after first hyphen)
     */
    public function hash(): string
    {
        return explode('-', $this->value, 2)[1];
    }

    /**
     * Check equality with another TaskId
     */
    public function equals(TaskId $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->value;
    }
}

<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Agent Identity Value Object
 *
 * Represents an agent (human or AI) working on tasks
 *
 * @psalm-immutable
 */
final readonly class Agent
{
    /**
     * @param  string  $id  Unique agent identifier
     * @param  string|null  $name  Optional human-readable name
     *
     * @throws InvalidArgumentException If id is empty
     */
    public function __construct(
        public string $id,
        public ?string $name = null,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Agent ID cannot be empty');
        }
    }

    /**
     * Create from ID string
     */
    public static function fromId(string $id): self
    {
        return new self($id);
    }

    /**
     * Create with both ID and name
     */
    public static function create(string $id, string $name): self
    {
        return new self($id, $name);
    }

    /**
     * Check equality with another Agent
     */
    public function equals(Agent $other): bool
    {
        return $this->id === $other->id;
    }

    /**
     * Get display name (name if available, otherwise ID)
     */
    public function displayName(): string
    {
        return $this->name ?? $this->id;
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->displayName();
    }
}

<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Model;

/**
 * Task Status Enum
 *
 * Represents the current state of a task in its lifecycle
 */
enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Closed = 'closed';

    /**
     * Check if task is open
     */
    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /**
     * Check if task is closed
     */
    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    /**
     * Check if task is in progress
     */
    public function isInProgress(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * Check if task is blocked
     */
    public function isBlocked(): bool
    {
        return $this === self::Blocked;
    }

    /**
     * Check if can transition to target status
     */
    public function canTransitionTo(TaskStatus $target): bool
    {
        return match ($this) {
            self::Open => in_array($target, [self::InProgress, self::Blocked, self::Closed], true),
            self::InProgress => in_array($target, [self::Blocked, self::Closed, self::Open], true),
            self::Blocked => in_array($target, [self::Open, self::InProgress, self::Closed], true),
            self::Closed => $target === self::Open, // Can reopen
        };
    }

    /**
     * Get all valid transitions from this status
     *
     * @return array<TaskStatus>
     */
    public function validTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::Blocked, self::Closed],
            self::InProgress => [self::Blocked, self::Closed, self::Open],
            self::Blocked => [self::Open, self::InProgress, self::Closed],
            self::Closed => [self::Open],
        };
    }
}

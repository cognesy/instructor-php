<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Model;

/**
 * Task Type Enum
 *
 * Represents the type/category of a task
 */
enum TaskType: string
{
    case Task = 'task';
    case Bug = 'bug';
    case Feature = 'feature';
    case Epic = 'epic';

    /**
     * Check if this is an epic
     */
    public function isEpic(): bool
    {
        return $this === self::Epic;
    }

    /**
     * Check if this is a bug
     */
    public function isBug(): bool
    {
        return $this === self::Bug;
    }

    /**
     * Check if this is a feature
     */
    public function isFeature(): bool
    {
        return $this === self::Feature;
    }

    /**
     * Check if this is a standard task
     */
    public function isTask(): bool
    {
        return $this === self::Task;
    }
}

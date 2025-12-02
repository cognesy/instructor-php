<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Exception;

use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;

/**
 * Concurrency Exception
 *
 * Thrown when a concurrent modification conflict is detected.
 * For example, two agents trying to claim the same task simultaneously.
 */
final class ConcurrencyException extends BeadsException
{
    public function __construct(
        string $message,
        public readonly ?TaskId $taskId = null,
    ) {
        parent::__construct($message);
    }

    public static function taskModified(TaskId $id): self
    {
        return new self(
            "Task {$id->value} was modified by another process",
            $id,
        );
    }

    public static function taskAlreadyClaimed(TaskId $id): self
    {
        return new self(
            "Task {$id->value} was already claimed by another agent",
            $id,
        );
    }
}

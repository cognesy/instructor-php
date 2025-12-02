<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Exception;

use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;

/**
 * Task Not Found Exception
 *
 * Thrown when attempting to access a task that doesn't exist.
 */
final class TaskNotFoundException extends BeadsException
{
    public function __construct(
        public readonly TaskId $taskId,
        string $message = '',
    ) {
        $msg = $message !== ''
            ? $message
            : "Task not found: {$taskId->value}";

        parent::__construct($msg);
    }

    public static function forId(TaskId $id): self
    {
        return new self($id);
    }
}

<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\CreateTask;

use Cognesy\Auxiliary\Beads\Domain\Model\Task;

/**
 * Create Task Result
 *
 * Result of creating a task.
 */
final readonly class CreateTaskResult
{
    public function __construct(
        public bool $success,
        public ?Task $task = null,
        public ?string $error = null,
    ) {}

    public static function success(Task $task): self
    {
        return new self(true, $task, null);
    }

    public static function failure(string $error): self
    {
        return new self(false, null, $error);
    }
}

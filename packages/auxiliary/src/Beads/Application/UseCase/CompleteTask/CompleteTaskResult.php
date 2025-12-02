<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\CompleteTask;

use Cognesy\Auxiliary\Beads\Domain\Model\Task;

/**
 * Complete Task Result
 *
 * Result of completing a task.
 */
final readonly class CompleteTaskResult
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

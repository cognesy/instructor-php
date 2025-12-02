<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\CompleteTask;

use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;

/**
 * Complete Task Command
 *
 * Command to mark a task as completed.
 */
final readonly class CompleteTaskCommand
{
    public function __construct(
        public TaskId $taskId,
        public ?string $completionNote = null,
    ) {}
}

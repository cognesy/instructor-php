<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\DataObjects;

use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskStatus;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;

/**
 * Task Data DTO
 *
 * Data transfer object for task information.
 */
final readonly class TaskData
{
    /**
     * @param  array<string>  $labels
     */
    public function __construct(
        public string $id,
        public string $title,
        public TaskType $type,
        public TaskStatus $status,
        public Priority $priority,
        public ?string $description = null,
        public ?string $assigneeId = null,
        public array $labels = [],
    ) {}

    /**
     * Create from domain Task entity
     */
    public static function fromTask(Task $task): self
    {
        return new self(
            id: $task->id()->value,
            title: $task->title(),
            type: $task->type(),
            status: $task->status(),
            priority: $task->priority(),
            description: $task->description(),
            assigneeId: $task->assignee()?->id,
            labels: $task->labels(),
        );
    }
}

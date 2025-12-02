<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\DataObjects;

use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;

/**
 * Create Task Data DTO
 *
 * Data transfer object for creating a task.
 */
final readonly class CreateTaskData
{
    /**
     * @param  array<string>  $labels
     */
    public function __construct(
        public string $title,
        public TaskType $type,
        public Priority $priority,
        public ?string $description = null,
        public ?string $assigneeId = null,
        public array $labels = [],
    ) {}

    /**
     * Create from array with validation
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['title']) || ! is_string($data['title'])) {
            throw new \InvalidArgumentException('Title is required and must be a string');
        }

        if (! isset($data['type']) || ! is_string($data['type'])) {
            throw new \InvalidArgumentException('Type is required and must be a string');
        }

        if (! isset($data['priority']) || ! is_int($data['priority'])) {
            throw new \InvalidArgumentException('Priority is required and must be an integer');
        }

        return new self(
            title: $data['title'],
            type: TaskType::from($data['type']),
            priority: Priority::fromInt($data['priority']),
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : null,
            assigneeId: isset($data['assignee_id']) && is_string($data['assignee_id']) ? $data['assignee_id'] : null,
            labels: isset($data['labels']) && is_array($data['labels']) ? $data['labels'] : [],
        );
    }
}

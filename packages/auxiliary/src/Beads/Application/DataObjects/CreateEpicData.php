<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\DataObjects;

use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;

/**
 * Create Epic Data DTO
 *
 * Data transfer object for creating an epic with subtasks.
 */
final readonly class CreateEpicData
{
    /**
     * @param  array<string>  $labels
     * @param  array<SubtaskData>  $subtasks
     */
    public function __construct(
        public string $title,
        public Priority $priority,
        public ?string $description = null,
        public ?string $assigneeId = null,
        public array $labels = [],
        public array $subtasks = [],
    ) {}

    /**
     * Create from array with validation
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['title']) || ! is_string($data['title'])) {
            throw new \InvalidArgumentException('Epic title is required and must be a string');
        }

        if (! isset($data['priority']) || ! is_int($data['priority'])) {
            throw new \InvalidArgumentException('Epic priority is required and must be an integer');
        }

        $subtasks = [];
        if (isset($data['subtasks']) && is_array($data['subtasks'])) {
            foreach ($data['subtasks'] as $subtaskData) {
                if (! is_array($subtaskData)) {
                    throw new \InvalidArgumentException('Each subtask must be an array');
                }
                $subtasks[] = SubtaskData::fromArray($subtaskData);
            }
        }

        return new self(
            title: $data['title'],
            priority: Priority::fromInt($data['priority']),
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : null,
            assigneeId: isset($data['assignee_id']) && is_string($data['assignee_id']) ? $data['assignee_id'] : null,
            labels: isset($data['labels']) && is_array($data['labels']) ? $data['labels'] : [],
            subtasks: $subtasks,
        );
    }
}

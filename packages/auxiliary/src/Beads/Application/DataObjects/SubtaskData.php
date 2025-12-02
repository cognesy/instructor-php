<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\DataObjects;

use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;

/**
 * Subtask Data DTO
 *
 * Data transfer object for creating a subtask within an epic.
 */
final readonly class SubtaskData
{
    public function __construct(
        public string $title,
        public TaskType $type,
        public Priority $priority,
        public ?string $description = null,
    ) {}

    /**
     * Create from array with validation
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['title']) || ! is_string($data['title'])) {
            throw new \InvalidArgumentException('Subtask title is required and must be a string');
        }

        if (! isset($data['type']) || ! is_string($data['type'])) {
            throw new \InvalidArgumentException('Subtask type is required and must be a string');
        }

        if (! isset($data['priority']) || ! is_int($data['priority'])) {
            throw new \InvalidArgumentException('Subtask priority is required and must be an integer');
        }

        return new self(
            title: $data['title'],
            type: TaskType::from($data['type']),
            priority: Priority::fromInt($data['priority']),
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : null,
        );
    }

    /**
     * Convert to array format for CreateEpicCommand
     *
     * @return array{title: string, type: string, priority: int, description?: string}
     */
    public function toArray(): array
    {
        $data = [
            'title' => $this->title,
            'type' => $this->type->value,
            'priority' => $this->priority->value,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }
}

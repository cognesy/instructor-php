<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\DataObjects;

use Cognesy\Auxiliary\Beads\Domain\Model\TaskStatus;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;

/**
 * Update Task Data DTO
 *
 * Data transfer object for updating a task.
 */
final readonly class UpdateTaskData
{
    public function __construct(
        public ?TaskStatus $status = null,
        public ?Priority $priority = null,
        public ?string $assigneeId = null,
    ) {}

    /**
     * Create from array with validation
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $status = null;
        if (isset($data['status'])) {
            if (! is_string($data['status'])) {
                throw new \InvalidArgumentException('Status must be a string');
            }
            $status = TaskStatus::from($data['status']);
        }

        $priority = null;
        if (isset($data['priority'])) {
            if (! is_int($data['priority'])) {
                throw new \InvalidArgumentException('Priority must be an integer');
            }
            $priority = Priority::fromInt($data['priority']);
        }

        $assigneeId = null;
        if (array_key_exists('assignee_id', $data)) {
            $value = $data['assignee_id'];
            if (! is_string($value) && $value !== null) {
                throw new \InvalidArgumentException('Assignee ID must be a string or null');
            }
            $assigneeId = is_string($value) ? $value : null;
        }

        return new self(
            status: $status,
            priority: $priority,
            assigneeId: $assigneeId,
        );
    }

    public function hasChanges(): bool
    {
        return $this->status !== null || $this->priority !== null || $this->assigneeId !== null;
    }
}

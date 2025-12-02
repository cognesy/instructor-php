<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Model;

use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Task Entity
 *
 * Represents a single task/issue with business rules for state transitions
 * and queries. Immutable - all state changes return new instances.
 */
final class Task
{
    /**
     * @param  TaskId  $id  Unique task identifier
     * @param  string  $title  Task title
     * @param  TaskStatus  $status  Current status
     * @param  TaskType  $type  Task type (task/bug/feature/epic)
     * @param  Priority  $priority  Priority level (0-4)
     * @param  Agent|null  $assignee  Assigned agent (null if unassigned)
     * @param  string|null  $description  Full description
     * @param  array<string>  $labels  Tags/labels
     * @param  DateTimeImmutable  $createdAt  Creation timestamp
     * @param  DateTimeImmutable|null  $updatedAt  Last update timestamp
     * @param  DateTimeImmutable|null  $closedAt  Closed timestamp
     */
    private function __construct(
        private TaskId $id,
        private string $title,
        private TaskStatus $status,
        private TaskType $type,
        private Priority $priority,
        private ?Agent $assignee,
        private ?string $description,
        private array $labels,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $closedAt,
    ) {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Task title cannot be empty');
        }
    }

    /**
     * Create new task (factory method)
     *
     * @param  array<string>  $labels
     */
    public static function create(
        TaskId $id,
        string $title,
        TaskType $type,
        Priority $priority,
        ?string $description = null,
        array $labels = [],
    ): self {
        return new self(
            id: $id,
            title: $title,
            status: TaskStatus::Open,
            type: $type,
            priority: $priority,
            assignee: null,
            description: $description,
            labels: $labels,
            createdAt: new DateTimeImmutable,
            updatedAt: null,
            closedAt: null,
        );
    }

    /**
     * Claim task (assign to agent, set status to in_progress)
     *
     * @throws InvalidArgumentException If task cannot be claimed
     */
    public function claim(Agent $agent): self
    {
        if (! $this->canBeClaimed()) {
            throw new InvalidArgumentException(
                "Cannot claim task in {$this->status->value} state"
            );
        }

        return new self(
            id: $this->id,
            title: $this->title,
            status: TaskStatus::InProgress,
            type: $this->type,
            priority: $this->priority,
            assignee: $agent,
            description: $this->description,
            labels: $this->labels,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable,
            closedAt: null,
        );
    }

    /**
     * Start task (set status to in_progress, requires assignee)
     *
     * @throws InvalidArgumentException If task cannot be started
     */
    public function start(): self
    {
        if (! $this->canBeStarted()) {
            throw new InvalidArgumentException(
                'Cannot start task: must be open and assigned'
            );
        }

        return new self(
            id: $this->id,
            title: $this->title,
            status: TaskStatus::InProgress,
            type: $this->type,
            priority: $this->priority,
            assignee: $this->assignee,
            description: $this->description,
            labels: $this->labels,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable,
            closedAt: null,
        );
    }

    /**
     * Complete task (close with reason)
     *
     * @throws InvalidArgumentException If task cannot be completed
     */
    public function complete(string $reason): self
    {
        if (! $this->canBeCompleted()) {
            throw new InvalidArgumentException(
                "Cannot complete task in {$this->status->value} state"
            );
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Completion reason cannot be empty');
        }

        $now = new DateTimeImmutable;

        return new self(
            id: $this->id,
            title: $this->title,
            status: TaskStatus::Closed,
            type: $this->type,
            priority: $this->priority,
            assignee: $this->assignee,
            description: $this->description,
            labels: $this->labels,
            createdAt: $this->createdAt,
            updatedAt: $now,
            closedAt: $now,
        );
    }

    /**
     * Block task (set status to blocked with reason)
     *
     * @throws InvalidArgumentException If task cannot be blocked
     */
    public function block(string $reason): self
    {
        if (! $this->status->canTransitionTo(TaskStatus::Blocked)) {
            throw new InvalidArgumentException(
                "Cannot block task from {$this->status->value} state"
            );
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Block reason cannot be empty');
        }

        return new self(
            id: $this->id,
            title: $this->title,
            status: TaskStatus::Blocked,
            type: $this->type,
            priority: $this->priority,
            assignee: $this->assignee,
            description: $this->description,
            labels: $this->labels,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable,
            closedAt: null,
        );
    }

    /**
     * Unblock task (return to in_progress if assigned, otherwise open)
     *
     * @throws InvalidArgumentException If task is not blocked
     */
    public function unblock(): self
    {
        if ($this->status !== TaskStatus::Blocked) {
            throw new InvalidArgumentException('Task is not blocked');
        }

        // Return to in_progress if assigned, otherwise open
        $newStatus = $this->assignee !== null
            ? TaskStatus::InProgress
            : TaskStatus::Open;

        return new self(
            id: $this->id,
            title: $this->title,
            status: $newStatus,
            type: $this->type,
            priority: $this->priority,
            assignee: $this->assignee,
            description: $this->description,
            labels: $this->labels,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable,
            closedAt: null,
        );
    }

    /**
     * Abandon task (unassign and return to open)
     *
     * @throws InvalidArgumentException If task is not in progress
     */
    public function abandon(): self
    {
        if ($this->status !== TaskStatus::InProgress) {
            throw new InvalidArgumentException(
                "Cannot abandon task in {$this->status->value} state"
            );
        }

        return new self(
            id: $this->id,
            title: $this->title,
            status: TaskStatus::Open,
            type: $this->type,
            priority: $this->priority,
            assignee: null,
            description: $this->description,
            labels: $this->labels,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable,
            closedAt: null,
        );
    }

    /**
     * Check if task is open
     */
    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * Check if task is closed
     */
    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    /**
     * Check if task is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status->isInProgress();
    }

    /**
     * Check if task is blocked
     */
    public function isBlocked(): bool
    {
        return $this->status->isBlocked();
    }

    /**
     * Check if task is assigned to specific agent
     */
    public function isAssignedTo(Agent $agent): bool
    {
        return $this->assignee !== null && $this->assignee->equals($agent);
    }

    /**
     * Check if task can be claimed (open and unassigned)
     */
    public function canBeClaimed(): bool
    {
        return $this->status === TaskStatus::Open && $this->assignee === null;
    }

    /**
     * Check if task can be started (open and assigned)
     */
    public function canBeStarted(): bool
    {
        return $this->status === TaskStatus::Open && $this->assignee !== null;
    }

    /**
     * Check if task can be completed (not closed or blocked)
     */
    public function canBeCompleted(): bool
    {
        return ! $this->status->isClosed() && ! $this->status->isBlocked();
    }

    /**
     * Get task ID
     */
    public function id(): TaskId
    {
        return $this->id;
    }

    /**
     * Get task title
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Get task status
     */
    public function status(): TaskStatus
    {
        return $this->status;
    }

    /**
     * Get task type
     */
    public function type(): TaskType
    {
        return $this->type;
    }

    /**
     * Get task priority
     */
    public function priority(): Priority
    {
        return $this->priority;
    }

    /**
     * Get assigned agent (null if unassigned)
     */
    public function assignee(): ?Agent
    {
        return $this->assignee;
    }

    /**
     * Get task description (null if none)
     */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * Get task labels
     *
     * @return array<string>
     */
    public function labels(): array
    {
        return $this->labels;
    }

    /**
     * Get creation timestamp
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get last update timestamp (null if never updated)
     */
    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Get closed timestamp (null if not closed)
     */
    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }
}

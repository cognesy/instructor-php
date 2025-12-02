<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Collection;

use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskStatus;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Task Collection
 *
 * Rich domain collection for Task entities with filtering, mapping, and sorting
 *
 * @implements IteratorAggregate<int, Task>
 */
final class TaskCollection implements Countable, IteratorAggregate
{
    /**
     * @param  array<Task>  $items
     */
    public function __construct(
        private array $items = [],
    ) {}

    /**
     * Create from array of tasks
     *
     * @param  array<Task>  $tasks
     */
    public static function from(array $tasks): self
    {
        return new self($tasks);
    }

    /**
     * Create empty collection
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Add task to collection
     */
    public function add(Task $task): self
    {
        $items = $this->items;
        $items[] = $task;

        return new self($items);
    }

    /**
     * Filter tasks by predicate
     *
     * @param  callable(Task): bool  $predicate
     */
    public function filter(callable $predicate): self
    {
        return new self(array_filter($this->items, $predicate));
    }

    /**
     * Map tasks to array
     *
     * @template T
     *
     * @param  callable(Task): T  $mapper
     * @return array<T>
     */
    public function map(callable $mapper): array
    {
        return array_map($mapper, $this->items);
    }

    /**
     * Filter tasks in progress
     */
    public function inProgress(): self
    {
        return $this->filter(fn (Task $task) => $task->isInProgress());
    }

    /**
     * Filter open tasks
     */
    public function open(): self
    {
        return $this->filter(fn (Task $task) => $task->isOpen());
    }

    /**
     * Filter closed tasks
     */
    public function closed(): self
    {
        return $this->filter(fn (Task $task) => $task->isClosed());
    }

    /**
     * Filter blocked tasks
     */
    public function blocked(): self
    {
        return $this->filter(fn (Task $task) => $task->isBlocked());
    }

    /**
     * Filter tasks by status
     */
    public function withStatus(TaskStatus $status): self
    {
        return $this->filter(fn (Task $task) => $task->status() === $status);
    }

    /**
     * Filter high priority tasks (critical or high)
     */
    public function highPriority(): self
    {
        return $this->filter(fn (Task $task) => $task->priority()->value <= 1
        );
    }

    /**
     * Filter tasks by minimum priority level
     */
    public function withPriority(Priority $minPriority): self
    {
        return $this->filter(fn (Task $task) => $task->priority()->value <= $minPriority->value
        );
    }

    /**
     * Filter tasks with specific label
     */
    public function withLabel(string $label): self
    {
        return $this->filter(fn (Task $task) => in_array($label, $task->labels(), true)
        );
    }

    /**
     * Filter tasks assigned to agent
     */
    public function assignedTo(Agent $agent): self
    {
        return $this->filter(fn (Task $task) => $task->isAssignedTo($agent));
    }

    /**
     * Filter unassigned tasks
     */
    public function unassigned(): self
    {
        return $this->filter(fn (Task $task) => $task->assignee() === null);
    }

    /**
     * Filter tasks that can be claimed
     */
    public function claimable(): self
    {
        return $this->filter(fn (Task $task) => $task->canBeClaimed());
    }

    /**
     * Sort tasks by priority (highest first)
     */
    public function sortByPriority(): self
    {
        $items = $this->items;
        usort($items, fn (Task $a, Task $b) => $a->priority()->value <=> $b->priority()->value
        );

        return new self($items);
    }

    /**
     * Sort tasks by creation date (newest first)
     */
    public function sortByNewest(): self
    {
        $items = $this->items;
        usort($items, fn (Task $a, Task $b) => $b->createdAt() <=> $a->createdAt()
        );

        return new self($items);
    }

    /**
     * Sort tasks by creation date (oldest first)
     */
    public function sortByOldest(): self
    {
        $items = $this->items;
        usort($items, fn (Task $a, Task $b) => $a->createdAt() <=> $b->createdAt()
        );

        return new self($items);
    }

    /**
     * Get first task or null
     */
    public function first(): ?Task
    {
        return $this->items[0] ?? null;
    }

    /**
     * Get last task or null
     */
    public function last(): ?Task
    {
        $items = $this->items;

        return empty($items) ? null : end($items);
    }

    /**
     * Find task by ID
     */
    public function find(TaskId $id): ?Task
    {
        foreach ($this->items as $task) {
            if ($task->id()->equals($id)) {
                return $task;
            }
        }

        return null;
    }

    /**
     * Check if collection contains task
     */
    public function contains(Task $needle): bool
    {
        foreach ($this->items as $task) {
            if ($task->id()->equals($needle->id())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if collection is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Check if collection is not empty
     */
    public function isNotEmpty(): bool
    {
        return ! empty($this->items);
    }

    /**
     * Get collection as array
     *
     * @return array<Task>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Get array of task IDs
     *
     * @return array<TaskId>
     */
    public function ids(): array
    {
        return $this->map(fn (Task $task) => $task->id());
    }

    /**
     * Count tasks in collection
     */
    #[\Override]
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get iterator
     *
     * @return Traversable<int, Task>
     */
    #[\Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}

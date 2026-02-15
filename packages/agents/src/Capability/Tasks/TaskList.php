<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Tasks;

use InvalidArgumentException;

final readonly class TaskList
{
    /** @var list<Task> */
    private array $tasks;

    /** @param list<Task> $tasks */
    private function __construct(array $tasks)
    {
        $this->tasks = $tasks;
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public static function fromArray(array $data): self
    {
        $tasks = [];
        foreach ($data as $item) {
            $tasks[] = Task::fromArray($item);
        }

        return self::empty()->withTasks($tasks);
    }

    /** @param list<Task> $tasks */
    public function withTasks(array $tasks): self
    {
        $this->assertValidCount($tasks);
        $this->assertSingleInProgress($tasks);

        return new self($tasks);
    }

    /** @return list<Task> */
    public function all(): array
    {
        return $this->tasks;
    }

    public function isEmpty(): bool
    {
        return $this->tasks === [];
    }

    public function count(): int
    {
        return count($this->tasks);
    }

    public function countByStatus(TaskStatus $status): int
    {
        $count = 0;
        foreach ($this->tasks as $task) {
            if ($task->status === $status) {
                $count++;
            }
        }

        return $count;
    }

    public function currentInProgress(): ?Task
    {
        foreach ($this->tasks as $task) {
            if ($task->status === TaskStatus::InProgress) {
                return $task;
            }
        }

        return null;
    }

    public function render(): string
    {
        if ($this->tasks === []) {
            return '(no tasks)';
        }

        $lines = [];
        foreach ($this->tasks as $index => $task) {
            $lines[] = ($index + 1) . '. ' . $task->render();
        }

        return implode("\n", $lines);
    }

    public function renderSummary(): string
    {
        $total = $this->count();
        $completed = $this->countByStatus(TaskStatus::Completed);
        $inProgress = $this->countByStatus(TaskStatus::InProgress);
        $pending = $this->countByStatus(TaskStatus::Pending);

        return sprintf(
            'Tasks: %d/%d completed, %d in progress, %d pending',
            $completed,
            $total,
            $inProgress,
            $pending,
        );
    }

    public function toArray(): array
    {
        $items = [];
        foreach ($this->tasks as $task) {
            $items[] = $task->toArray();
        }

        return $items;
    }

    /** @param list<Task> $tasks */
    private function assertValidCount(array $tasks): void
    {
        if (count($tasks) > 20) {
            throw new InvalidArgumentException('Maximum 20 tasks allowed');
        }
    }

    /** @param list<Task> $tasks */
    private function assertSingleInProgress(array $tasks): void
    {
        $inProgress = 0;
        foreach ($tasks as $task) {
            if ($task->status === TaskStatus::InProgress) {
                $inProgress++;
            }
        }

        if ($inProgress > 1) {
            throw new InvalidArgumentException('Only 1 task can be in_progress');
        }
    }
}

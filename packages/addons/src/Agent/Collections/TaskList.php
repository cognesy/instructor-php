<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Collections;

use Cognesy\Addons\Agent\Data\Task;
use Cognesy\Addons\Agent\Enums\TaskStatus;
use InvalidArgumentException;

final readonly class TaskList
{
    private const MAX_TASKS = 20;

    /** @var list<Task> */
    private array $tasks;

    /** @param list<Task> $tasks */
    private function __construct(array $tasks) {
        $this->tasks = $tasks;
    }

    public static function empty(): self {
        return new self([]);
    }

    /** @param list<array> $data */
    public static function fromArray(array $data): self {
        $tasks = array_map(
            fn(array $item) => Task::fromArray($item),
            $data
        );
        return new self($tasks);
    }

    /** @return list<array> */
    public function toArray(): array {
        return array_map(
            fn(Task $task) => $task->toArray(),
            $this->tasks
        );
    }

    /** @param list<Task> $tasks */
    public function withTasks(array $tasks): self {
        $this->validateTasks($tasks);
        return new self($tasks);
    }

    /** @return list<Task> */
    public function all(): array {
        return $this->tasks;
    }

    public function count(): int {
        return count($this->tasks);
    }

    public function isEmpty(): bool {
        return $this->count() === 0;
    }

    public function countByStatus(TaskStatus $status): int {
        return count(array_filter(
            $this->tasks,
            fn(Task $task) => $task->status === $status
        ));
    }

    public function currentInProgress(): ?Task {
        foreach ($this->tasks as $task) {
            if ($task->status === TaskStatus::InProgress) {
                return $task;
            }
        }
        return null;
    }

    public function render(): string {
        if ($this->isEmpty()) {
            return "(no tasks)";
        }

        $lines = [];
        foreach ($this->tasks as $index => $task) {
            $num = $index + 1;
            $lines[] = "{$num}. {$task->render()}";
        }

        return implode("\n", $lines);
    }

    public function renderSummary(): string {
        $total = $this->count();
        $completed = $this->countByStatus(TaskStatus::Completed);
        $inProgress = $this->countByStatus(TaskStatus::InProgress);
        $pending = $this->countByStatus(TaskStatus::Pending);

        return "Tasks: {$completed}/{$total} completed, {$inProgress} in progress, {$pending} pending";
    }

    /** @param list<Task> $tasks */
    private function validateTasks(array $tasks): void {
        if (count($tasks) > self::MAX_TASKS) {
            throw new InvalidArgumentException(
                "Maximum " . self::MAX_TASKS . " tasks allowed, got " . count($tasks)
            );
        }

        $inProgressCount = 0;
        foreach ($tasks as $task) {
            if ($task->status === TaskStatus::InProgress) {
                $inProgressCount++;
            }
        }

        if ($inProgressCount > 1) {
            throw new InvalidArgumentException(
                "Only 1 task can be in_progress at a time, got {$inProgressCount}"
            );
        }
    }
}

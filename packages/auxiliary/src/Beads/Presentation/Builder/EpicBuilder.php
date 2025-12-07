<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Builder;

use Cognesy\Auxiliary\Beads\Application\UseCase\CreateEpic\CreateEpicCommand;
use Cognesy\Auxiliary\Beads\Application\UseCase\CreateEpic\CreateEpicHandler;
use Cognesy\Auxiliary\Beads\Application\UseCase\CreateEpic\CreateEpicResult;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;

/**
 * Epic Builder
 *
 * Fluent API for building epics with subtasks.
 */
final class EpicBuilder
{
    private ?string $title = null;

    private Priority $priority;

    private ?string $description = null;

    private ?Agent $assignee = null;

    /** @var array<string> */
    private array $labels = [];

    /** @var array<array{title: string, type: string, priority: int, description?: string}> */
    private array $subtasks = [];

    public function __construct(
        private readonly CreateEpicHandler $createEpicHandler,
    ) {
        $this->priority = Priority::medium();
    }

    /**
     * Set epic title
     */
    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set epic priority
     */
    public function priority(Priority $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Set priority to critical
     */
    public function critical(): self
    {
        $this->priority = Priority::critical();

        return $this;
    }

    /**
     * Set priority to high
     */
    public function high(): self
    {
        $this->priority = Priority::high();

        return $this;
    }

    /**
     * Set epic description
     */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Assign epic to agent
     */
    public function assignTo(Agent $agent): self
    {
        $this->assignee = $agent;

        return $this;
    }

    /**
     * Add label
     */
    public function withLabel(string $label): self
    {
        $this->labels[] = $label;

        return $this;
    }

    /**
     * Add subtask using callback
     */
    /**
     * @param callable(SubtaskBuilder): void $callback
     */
    public function subtask(callable $callback): self
    {
        $builder = new SubtaskBuilder;
        $callback($builder);

        $subtask = $builder->build();
        if ($subtask !== null) {
            $this->subtasks[] = $subtask;
        }

        return $this;
    }

    /**
     * Add multiple parallel tasks (all block the epic)
     *
     * @param  array<callable(SubtaskBuilder): void>  $callbacks
     */
    public function parallelTasks(array $callbacks): self
    {
        foreach ($callbacks as $callback) {
            $this->subtask($callback);
        }

        return $this;
    }

    /**
     * Add sequential tasks (each blocks the next)
     * Note: Dependencies are handled by bd CLI separately
     *
     * @param  array<callable(SubtaskBuilder): void>  $callbacks
     */
    public function sequentialTasks(array $callbacks): self
    {
        foreach ($callbacks as $callback) {
            $this->subtask($callback);
        }

        return $this;
    }

    /**
     * Create the epic with subtasks
     */
    public function create(): CreateEpicResult
    {
        if ($this->title === null) {
            return CreateEpicResult::failure('Epic title is required');
        }

        return $this->createEpicHandler->handle(
            new CreateEpicCommand(
                title: $this->title,
                priority: $this->priority,
                description: $this->description,
                labels: $this->labels,
                assignee: $this->assignee,
                subtasks: $this->subtasks,
            )
        );
    }
}

/**
 * Subtask Builder
 *
 * Helper builder for defining subtasks in an epic.
 */
final class SubtaskBuilder
{
    private ?string $title = null;

    private TaskType $type = TaskType::Task;

    private Priority $priority;

    private ?string $description = null;

    public function __construct()
    {
        $this->priority = Priority::medium();
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function type(TaskType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function asBug(): self
    {
        $this->type = TaskType::Bug;

        return $this;
    }

    public function asFeature(): self
    {
        $this->type = TaskType::Feature;

        return $this;
    }

    public function priority(Priority $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function critical(): self
    {
        $this->priority = Priority::critical();

        return $this;
    }

    public function high(): self
    {
        $this->priority = Priority::high();

        return $this;
    }

    public function medium(): self
    {
        $this->priority = Priority::medium();

        return $this;
    }

    public function low(): self
    {
        $this->priority = Priority::low();

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Build subtask array
     *
     * @return array{title: string, type: string, priority: int, description?: string}|null
     */
    public function build(): ?array
    {
        if ($this->title === null) {
            return null;
        }

        $subtask = [
            'title' => $this->title,
            'type' => $this->type->value,
            'priority' => $this->priority->value,
        ];

        if ($this->description !== null) {
            $subtask['description'] = $this->description;
        }

        return $subtask;
    }
}

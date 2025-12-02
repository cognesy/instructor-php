<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Builder;

use Cognesy\Auxiliary\Beads\Application\UseCase\ClaimTask\ClaimTaskCommand;
use Cognesy\Auxiliary\Beads\Application\UseCase\ClaimTask\ClaimTaskHandler;
use Cognesy\Auxiliary\Beads\Application\UseCase\ClaimTask\ClaimTaskResult;
use Cognesy\Auxiliary\Beads\Application\UseCase\CreateTask\CreateTaskCommand;
use Cognesy\Auxiliary\Beads\Application\UseCase\CreateTask\CreateTaskHandler;
use Cognesy\Auxiliary\Beads\Application\UseCase\CreateTask\CreateTaskResult;
use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;
use Cognesy\Auxiliary\Beads\Infrastructure\Client\BdClient;

/**
 * Task Builder
 *
 * Fluent API for building and creating tasks.
 */
final class TaskBuilder
{
    private ?string $title = null;

    private TaskType $type = TaskType::Task;

    private ?Priority $priority = null;

    private ?string $description = null;

    private ?Agent $assignee = null;

    /** @var array<string> */
    private array $labels = [];

    /** @var array<string> */
    private array $dependencies = [];

    public function __construct(
        private readonly CreateTaskHandler $createTaskHandler,
        private readonly ClaimTaskHandler $claimTaskHandler,
        private readonly BdClient $bdClient,
    ) {}

    /**
     * Set task title
     */
    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set task type
     */
    public function type(TaskType $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set task as bug
     */
    public function asBug(): self
    {
        $this->type = TaskType::Bug;

        return $this;
    }

    /**
     * Set task as feature
     */
    public function asFeature(): self
    {
        $this->type = TaskType::Feature;

        return $this;
    }

    /**
     * Set task as epic
     */
    public function asEpic(): self
    {
        $this->type = TaskType::Epic;

        return $this;
    }

    /**
     * Set task priority
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
     * Set priority to medium
     */
    public function medium(): self
    {
        $this->priority = Priority::medium();

        return $this;
    }

    /**
     * Set priority to low
     */
    public function low(): self
    {
        $this->priority = Priority::low();

        return $this;
    }

    /**
     * Set task description
     */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Assign task to agent
     */
    public function assignTo(Agent $agent): self
    {
        $this->assignee = $agent;

        return $this;
    }

    /**
     * Assign task to agent by ID
     */
    public function assignToId(string $agentId, string $agentName = 'Agent'): self
    {
        $this->assignee = Agent::create($agentId, $agentName);

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
     * Add multiple labels
     *
     * @param  array<string>  $labels
     */
    public function withLabels(array $labels): self
    {
        $this->labels = array_merge($this->labels, $labels);

        return $this;
    }

    /**
     * Add dependency (this task depends on another)
     */
    public function dependsOn(string $taskId): self
    {
        $this->dependencies[] = $taskId;

        return $this;
    }

    /**
     * Create the task
     */
    public function create(): CreateTaskResult
    {
        if ($this->title === null) {
            return CreateTaskResult::failure('Title is required');
        }

        $result = $this->createTaskHandler->handle(
            new CreateTaskCommand(
                title: $this->title,
                type: $this->type,
                priority: $this->priority ?? Priority::medium(),
                description: $this->description,
                labels: $this->labels,
                assignee: $this->assignee,
            )
        );

        // Add dependencies if specified
        if ($result->success && $result->task !== null && count($this->dependencies) > 0) {
            $taskId = $result->task->id()->value;
            foreach ($this->dependencies as $dependency) {
                try {
                    $this->bdClient->addDependency($taskId, $dependency);
                } catch (\Throwable $e) {
                    // Log but don't fail the creation
                }
            }
        }

        return $result;
    }

    /**
     * Create and claim the task for an agent
     */
    public function createAndClaim(Agent $agent): ClaimTaskResult
    {
        $createResult = $this->create();

        if (! $createResult->success || $createResult->task === null) {
            return ClaimTaskResult::failure(
                $createResult->error ?? 'Failed to create task'
            );
        }

        return $this->claimTaskHandler->handle(
            new ClaimTaskCommand(
                taskId: $createResult->task->id(),
                agent: $agent,
            )
        );
    }

    /**
     * Get the created task (if available)
     */
    public function getTask(): ?Task
    {
        $result = $this->create();

        return $result->success ? $result->task : null;
    }
}

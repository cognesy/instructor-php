<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Facade;

use Cognesy\Auxiliary\Beads\Application\DataObjects\TaskData;
use Cognesy\Auxiliary\Beads\Application\Service\AgentContextService;
use Cognesy\Auxiliary\Beads\Application\Service\TaskQueryService;
use Cognesy\Auxiliary\Beads\Application\UseCase\CompleteTask\CompleteTaskCommand;
use Cognesy\Auxiliary\Beads\Application\UseCase\CompleteTask\CompleteTaskHandler;
use Cognesy\Auxiliary\Beads\Application\UseCase\CompleteTask\CompleteTaskResult;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskStatus;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;
use Cognesy\Auxiliary\Beads\Presentation\Builder\EpicBuilder;
use Cognesy\Auxiliary\Beads\Presentation\Builder\TaskBuilder;

/**
 * Beads Facade
 *
 * Main entry point for the Beads integration.
 * Provides fluent API for task management and agent context.
 */
final class Beads
{
    private ?Agent $currentAgent = null;

    public function __construct(
        private readonly TaskBuilder $taskBuilder,
        private readonly EpicBuilder $epicBuilder,
        private readonly TaskQueryService $taskQueryService,
        private readonly AgentContextService $agentContextService,
        private readonly CompleteTaskHandler $completeTaskHandler,
    ) {}

    /**
     * Set current agent context
     */
    public function as(Agent $agent): self
    {
        $this->currentAgent = $agent;

        return $this;
    }

    /**
     * Set current agent by ID
     */
    public function asAgent(string $agentId, string $agentName = 'Agent'): self
    {
        $this->currentAgent = Agent::create($agentId, $agentName);

        return $this;
    }

    /**
     * Get current agent
     */
    public function getCurrentAgent(): ?Agent
    {
        return $this->currentAgent;
    }

    /**
     * Start building a new task
     */
    public function task(): TaskBuilder
    {
        return clone $this->taskBuilder;
    }

    /**
     * Start building a new epic
     */
    public function epic(): EpicBuilder
    {
        return clone $this->epicBuilder;
    }

    /**
     * Find task by ID
     */
    public function find(string $taskId): ?TaskData
    {
        return $this->taskQueryService->getTaskById($taskId);
    }

    /**
     * Get next available tasks for current agent
     *
     * @return array<TaskData>
     */
    public function nextTask(int $limit = 5): array
    {
        if ($this->currentAgent === null) {
            return [];
        }

        return $this->agentContextService->getNextTasks($this->currentAgent, $limit);
    }

    /**
     * Get tasks assigned to current agent
     *
     * @return array<TaskData>
     */
    public function mine(): array
    {
        if ($this->currentAgent === null) {
            return [];
        }

        return $this->taskQueryService->getTasksByAssignee($this->currentAgent);
    }

    /**
     * Get available (ready) tasks
     *
     * @return array<TaskData>
     */
    public function available(int $limit = 10): array
    {
        return $this->taskQueryService->getReadyTasks($limit);
    }

    /**
     * Get all tasks
     *
     * @return array<TaskData>
     */
    public function all(): array
    {
        return $this->taskQueryService->getAllTasks();
    }

    /**
     * Get open tasks
     *
     * @return array<TaskData>
     */
    public function open(): array
    {
        return $this->taskQueryService->getTasksByStatus(TaskStatus::Open);
    }

    /**
     * Get in-progress tasks
     *
     * @return array<TaskData>
     */
    public function inProgress(): array
    {
        return $this->taskQueryService->getTasksByStatus(TaskStatus::InProgress);
    }

    /**
     * Get closed tasks
     *
     * @return array<TaskData>
     */
    public function closed(): array
    {
        return $this->taskQueryService->getTasksByStatus(TaskStatus::Closed);
    }

    /**
     * Complete a task
     */
    public function complete(string $taskId, ?string $note = null): CompleteTaskResult
    {
        return $this->completeTaskHandler->handle(
            new CompleteTaskCommand(
                taskId: new TaskId($taskId),
                completionNote: $note,
            )
        );
    }

    /**
     * Recover session for current agent
     *
     * @return array{has_active_tasks: bool, in_progress_tasks: array<TaskData>}
     */
    public function recoverSession(): array
    {
        if ($this->currentAgent === null) {
            return [
                'has_active_tasks' => false,
                'in_progress_tasks' => [],
            ];
        }

        return $this->agentContextService->recoverSession($this->currentAgent);
    }

    /**
     * Get full agent context (session + next tasks)
     *
     * @return array{
     *     has_active_tasks: bool,
     *     in_progress_tasks: array<TaskData>,
     *     next_available_tasks: array<TaskData>
     * }
     */
    public function context(int $nextTasksLimit = 5): array
    {
        if ($this->currentAgent === null) {
            return [
                'has_active_tasks' => false,
                'in_progress_tasks' => [],
                'next_available_tasks' => [],
            ];
        }

        return $this->agentContextService->getFullContext($this->currentAgent, $nextTasksLimit);
    }
}

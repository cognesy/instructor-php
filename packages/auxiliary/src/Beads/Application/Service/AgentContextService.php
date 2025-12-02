<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Service;

use Cognesy\Auxiliary\Beads\Application\DataObjects\TaskData;
use Cognesy\Auxiliary\Beads\Application\UseCase\GetNextTask\GetNextTaskHandler;
use Cognesy\Auxiliary\Beads\Application\UseCase\GetNextTask\GetNextTaskQuery;
use Cognesy\Auxiliary\Beads\Application\UseCase\RecoverSession\RecoverSessionHandler;
use Cognesy\Auxiliary\Beads\Application\UseCase\RecoverSession\RecoverSessionQuery;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;

/**
 * Agent Context Service
 *
 * Application service for agent session context and workflow.
 */
final readonly class AgentContextService
{
    public function __construct(
        private RecoverSessionHandler $recoverSessionHandler,
        private GetNextTaskHandler $getNextTaskHandler,
    ) {}

    /**
     * Recover agent session context
     *
     * @return array{has_active_tasks: bool, in_progress_tasks: array<TaskData>}
     */
    public function recoverSession(Agent $agent): array
    {
        $result = $this->recoverSessionHandler->handle(
            new RecoverSessionQuery($agent)
        );

        if (! $result->success) {
            return [
                'has_active_tasks' => false,
                'in_progress_tasks' => [],
            ];
        }

        $tasks = $result->inProgressTasks !== null
            ? array_map(
                fn ($task) => TaskData::fromTask($task),
                $result->inProgressTasks->toArray()
            )
            : [];

        return [
            'has_active_tasks' => $result->hasActiveTasks(),
            'in_progress_tasks' => $tasks,
        ];
    }

    /**
     * Get next available tasks for agent
     *
     * @return array<TaskData>
     */
    public function getNextTasks(Agent $agent, int $limit = 5): array
    {
        $result = $this->getNextTaskHandler->handle(
            new GetNextTaskQuery($agent, $limit)
        );

        if (! $result->success || $result->tasks === null) {
            return [];
        }

        return array_map(
            fn ($task) => TaskData::fromTask($task),
            $result->tasks->toArray()
        );
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
    public function getFullContext(Agent $agent, int $nextTasksLimit = 5): array
    {
        $session = $this->recoverSession($agent);
        $nextTasks = $this->getNextTasks($agent, $nextTasksLimit);

        return [
            'has_active_tasks' => $session['has_active_tasks'],
            'in_progress_tasks' => $session['in_progress_tasks'],
            'next_available_tasks' => $nextTasks,
        ];
    }
}

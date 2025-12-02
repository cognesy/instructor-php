<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Service;

use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Repository\TaskRepositoryInterface;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;

/**
 * Session Recovery Service
 *
 * Domain service for helping agents recover context after interruption.
 * Analyzes current state and recommends next actions.
 */
final readonly class SessionRecoveryService
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private DependencyService $dependencyService,
    ) {}

    /**
     * Get recommended action for agent
     *
     * Analyzes agent's current tasks and returns recommendation:
     * - 'resume_current_task' if agent has in-progress task
     * - 'find_new_task' if no current tasks or all blocked
     * - 'unblock_tasks' if agent has blocked tasks
     *
     * @return array{
     *     action: string,
     *     task: Task|null,
     *     reason: string
     * }
     */
    public function recommendAction(Agent $agent): array
    {
        $myTasks = $this->taskRepository->findByAssignee($agent);

        // Check for in-progress tasks
        $inProgress = $myTasks->inProgress();
        if ($inProgress->isNotEmpty()) {
            $task = $inProgress->sortByPriority()->first();

            return [
                'action' => 'resume_current_task',
                'task' => $task,
                'reason' => 'You have an in-progress task',
            ];
        }

        // Check for blocked tasks
        $blocked = $myTasks->blocked();
        if ($blocked->isNotEmpty()) {
            return [
                'action' => 'unblock_tasks',
                'task' => $blocked->sortByPriority()->first(),
                'reason' => 'You have blocked tasks that need attention',
            ];
        }

        // Check for open assigned tasks
        $open = $myTasks->open();
        if ($open->isNotEmpty()) {
            $task = $open->sortByPriority()->first();

            return [
                'action' => 'start_assigned_task',
                'task' => $task,
                'reason' => 'You have open tasks assigned to you',
            ];
        }

        // No current tasks - find new work
        return [
            'action' => 'find_new_task',
            'task' => null,
            'reason' => 'No current tasks - ready for new work',
        ];
    }

    /**
     * Get agent's current context summary
     *
     * @return array{
     *     total_tasks: int,
     *     in_progress: int,
     *     blocked: int,
     *     open: int,
     *     high_priority: int
     * }
     */
    public function getContext(Agent $agent): array
    {
        $myTasks = $this->taskRepository->findByAssignee($agent);

        return [
            'total_tasks' => $myTasks->count(),
            'in_progress' => $myTasks->inProgress()->count(),
            'blocked' => $myTasks->blocked()->count(),
            'open' => $myTasks->open()->count(),
            'high_priority' => $myTasks->highPriority()->count(),
        ];
    }

    /**
     * Find next available task for agent
     *
     * Returns highest priority task that agent can claim and start immediately
     * (no blockers).
     */
    public function findNextTask(Agent $agent, int $limit = 10): ?Task
    {
        $readyTasks = $this->taskRepository->findReady($limit);

        // Filter to claimable tasks with no blockers
        $available = $readyTasks->filter(function (Task $task) {
            return $task->canBeClaimed() && ! $this->dependencyService->hasBlockers($task);
        });

        // Return highest priority
        return $available->sortByPriority()->first();
    }
}

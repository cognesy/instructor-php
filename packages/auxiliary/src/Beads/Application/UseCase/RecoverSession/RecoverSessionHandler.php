<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\RecoverSession;

use Cognesy\Auxiliary\Beads\Domain\Repository\TaskRepositoryInterface;
use Throwable;

/**
 * Recover Session Handler
 *
 * Handles the query logic for recovering an agent's session.
 * Retrieves in-progress tasks assigned to the agent.
 */
final readonly class RecoverSessionHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
    ) {}

    public function handle(RecoverSessionQuery $query): RecoverSessionResult
    {
        try {
            // Get all tasks assigned to the agent
            $tasks = $this->taskRepository->findByAssignee($query->agent);

            // Filter for in-progress tasks only
            $inProgressTasks = $tasks->filter(fn ($task) => $task->isInProgress());

            return RecoverSessionResult::success($inProgressTasks);
        } catch (Throwable $e) {
            return RecoverSessionResult::failure(
                "Failed to recover session: {$e->getMessage()}"
            );
        }
    }
}

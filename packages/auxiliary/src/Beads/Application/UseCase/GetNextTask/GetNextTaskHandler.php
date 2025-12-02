<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\GetNextTask;

use Cognesy\Auxiliary\Beads\Domain\Repository\TaskRepositoryInterface;
use Throwable;

/**
 * Get Next Task Handler
 *
 * Handles the query logic for getting the next available task(s).
 * Uses bd ready command to find tasks that are unblocked and ready to work.
 */
final readonly class GetNextTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
    ) {}

    public function handle(GetNextTaskQuery $query): GetNextTaskResult
    {
        try {
            // Get ready tasks using bd ready command
            $tasks = $this->taskRepository->findReady($query->limit);

            return GetNextTaskResult::success($tasks);
        } catch (Throwable $e) {
            return GetNextTaskResult::failure(
                "Failed to get next task: {$e->getMessage()}"
            );
        }
    }
}

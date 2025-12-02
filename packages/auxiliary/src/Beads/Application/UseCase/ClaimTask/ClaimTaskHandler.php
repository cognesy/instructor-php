<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\ClaimTask;

use Cognesy\Auxiliary\Beads\Domain\Repository\TaskRepositoryInterface;
use Cognesy\Auxiliary\Beads\Domain\Service\TaskLifecycleService;
use Throwable;

/**
 * Claim Task Handler
 *
 * Handles the business logic for claiming a task.
 */
final readonly class ClaimTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private TaskLifecycleService $lifecycleService,
    ) {}

    public function handle(ClaimTaskCommand $command): ClaimTaskResult
    {
        try {
            // Find the task
            $task = $this->taskRepository->findById($command->taskId);

            if ($task === null) {
                return ClaimTaskResult::failure(
                    "Task {$command->taskId->value} not found"
                );
            }

            // Attempt to claim using lifecycle service
            $claimedTask = $this->lifecycleService->claimAndStart($task, $command->agent);

            if ($claimedTask === null) {
                return ClaimTaskResult::failure(
                    "Cannot claim task {$command->taskId->value} - task must be open and unassigned"
                );
            }

            // Persist the claimed task
            $this->taskRepository->save($claimedTask);

            return ClaimTaskResult::success($claimedTask);
        } catch (Throwable $e) {
            return ClaimTaskResult::failure(
                "Failed to claim task: {$e->getMessage()}"
            );
        }
    }
}

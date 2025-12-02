<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\CompleteTask;

use Cognesy\Auxiliary\Beads\Domain\Repository\TaskRepositoryInterface;
use Cognesy\Auxiliary\Beads\Domain\Service\TaskLifecycleService;
use Throwable;

/**
 * Complete Task Handler
 *
 * Handles the business logic for completing a task.
 */
final readonly class CompleteTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private TaskLifecycleService $lifecycleService,
    ) {}

    public function handle(CompleteTaskCommand $command): CompleteTaskResult
    {
        try {
            // Find the task
            $task = $this->taskRepository->findById($command->taskId);

            if ($task === null) {
                return CompleteTaskResult::failure(
                    "Task {$command->taskId->value} not found"
                );
            }

            // Complete the task using lifecycle service
            $completedTask = $this->lifecycleService->attemptComplete(
                $task,
                $command->completionNote ?? 'Completed'
            );

            if ($completedTask === null) {
                return CompleteTaskResult::failure(
                    "Cannot complete task {$command->taskId->value} - task must be in progress"
                );
            }

            // Persist the completed task
            $this->taskRepository->save($completedTask);

            return CompleteTaskResult::success($completedTask);
        } catch (Throwable $e) {
            return CompleteTaskResult::failure(
                "Failed to complete task: {$e->getMessage()}"
            );
        }
    }
}

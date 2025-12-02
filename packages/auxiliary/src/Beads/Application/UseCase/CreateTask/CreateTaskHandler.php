<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\CreateTask;

use Cognesy\Auxiliary\Beads\Infrastructure\Factory\TaskFactory;
use Throwable;

/**
 * Create Task Handler
 *
 * Handles the business logic for creating a new task.
 * Uses TaskFactory to create tasks via bd CLI with proper ID assignment.
 */
final readonly class CreateTaskHandler
{
    public function __construct(
        private TaskFactory $taskFactory,
    ) {}

    public function handle(CreateTaskCommand $command): CreateTaskResult
    {
        try {
            // Create task via factory (handles bd CLI interaction)
            $task = $command->assignee !== null
                ? $this->taskFactory->createWithAssignee(
                    title: $command->title,
                    type: $command->type,
                    priority: $command->priority,
                    assigneeId: $command->assignee->id,
                    description: $command->description,
                )
                : $this->taskFactory->create(
                    title: $command->title,
                    type: $command->type,
                    priority: $command->priority,
                    description: $command->description,
                    labels: $command->labels,
                );

            return CreateTaskResult::success($task);
        } catch (Throwable $e) {
            return CreateTaskResult::failure(
                "Failed to create task: {$e->getMessage()}"
            );
        }
    }
}

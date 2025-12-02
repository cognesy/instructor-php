<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\CreateEpic;

use Cognesy\Auxiliary\Beads\Domain\Collection\TaskCollection;
use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;
use Cognesy\Auxiliary\Beads\Infrastructure\Client\BdClient;
use Cognesy\Auxiliary\Beads\Infrastructure\Factory\TaskFactory;
use Throwable;

/**
 * Create Epic Handler
 *
 * Handles the business logic for creating an epic with subtasks.
 * Creates parent task first, then creates subtasks with dependencies.
 */
final readonly class CreateEpicHandler
{
    public function __construct(
        private TaskFactory $taskFactory,
        private BdClient $bdClient,
    ) {}

    public function handle(CreateEpicCommand $command): CreateEpicResult
    {
        try {
            // Create the epic (parent task)
            $epic = $command->assignee !== null
                ? $this->taskFactory->createWithAssignee(
                    title: $command->title,
                    type: TaskType::Epic,
                    priority: $command->priority,
                    assigneeId: $command->assignee->id,
                    description: $command->description,
                )
                : $this->taskFactory->create(
                    title: $command->title,
                    type: TaskType::Epic,
                    priority: $command->priority,
                    description: $command->description,
                    labels: $command->labels,
                );

            // Create subtasks and link them to the epic
            $subtasks = [];

            foreach ($command->subtasks as $subtaskData) {
                $subtask = $this->taskFactory->create(
                    title: $subtaskData['title'],
                    type: TaskType::from($subtaskData['type']),
                    priority: Priority::fromInt($subtaskData['priority']),
                    description: $subtaskData['description'] ?? null,
                    labels: [],
                );

                // Add dependency: epic blocks subtask
                $this->bdClient->addDependency(
                    blockedTaskId: $subtask->id()->value,
                    blockerTaskId: $epic->id()->value,
                );

                $subtasks[] = $subtask;
            }

            return CreateEpicResult::success($epic, TaskCollection::from($subtasks));
        } catch (Throwable $e) {
            return CreateEpicResult::failure(
                "Failed to create epic: {$e->getMessage()}"
            );
        }
    }
}

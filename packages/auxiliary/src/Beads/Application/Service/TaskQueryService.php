<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Service;

use Cognesy\Auxiliary\Beads\Application\DataObjects\TaskData;
use Cognesy\Auxiliary\Beads\Domain\Collection\TaskCollection;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskStatus;
use Cognesy\Auxiliary\Beads\Domain\Repository\TaskRepositoryInterface;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;

/**
 * Task Query Service
 *
 * Application service for task queries with DTO mapping and caching.
 */
final readonly class TaskQueryService
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
    ) {}

    /**
     * Get task by ID
     */
    public function getTaskById(string $taskId): ?TaskData
    {
        $task = $this->taskRepository->findById(new TaskId($taskId));

        return $task !== null ? TaskData::fromTask($task) : null;
    }

    /**
     * Get tasks by status
     *
     * @return array<TaskData>
     */
    public function getTasksByStatus(TaskStatus $status): array
    {
        $tasks = $this->taskRepository->findByStatus($status);

        return $this->mapToDataArray($tasks);
    }

    /**
     * Get tasks by assignee
     *
     * @return array<TaskData>
     */
    public function getTasksByAssignee(Agent $agent): array
    {
        $tasks = $this->taskRepository->findByAssignee($agent);

        return $this->mapToDataArray($tasks);
    }

    /**
     * Get ready tasks
     *
     * @return array<TaskData>
     */
    public function getReadyTasks(int $limit = 10): array
    {
        $tasks = $this->taskRepository->findReady($limit);

        return $this->mapToDataArray($tasks);
    }

    /**
     * Get all tasks
     *
     * @return array<TaskData>
     */
    public function getAllTasks(): array
    {
        $tasks = $this->taskRepository->findAll();

        return $this->mapToDataArray($tasks);
    }

    /**
     * Map TaskCollection to array of TaskData
     *
     * @return array<TaskData>
     */
    private function mapToDataArray(TaskCollection $tasks): array
    {
        return array_map(
            fn ($task) => TaskData::fromTask($task),
            $tasks->toArray()
        );
    }
}

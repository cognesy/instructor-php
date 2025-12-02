<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Repository;

use Cognesy\Auxiliary\Beads\Domain\Collection\CommentCollection;
use Cognesy\Auxiliary\Beads\Domain\Collection\TaskCollection;
use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskStatus;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;

/**
 * Task Repository Interface
 *
 * Pure domain contract for task persistence operations.
 * No implementation details - infrastructure layer handles execution.
 */
interface TaskRepositoryInterface
{
    /**
     * Find task by ID
     *
     * @return Task|null Null if task not found
     */
    public function findById(TaskId $id): ?Task;

    /**
     * Find multiple tasks by IDs
     *
     * @param  array<TaskId>  $ids
     */
    public function findMany(array $ids): TaskCollection;

    /**
     * Find tasks by status
     */
    public function findByStatus(TaskStatus $status): TaskCollection;

    /**
     * Find tasks assigned to agent
     */
    public function findByAssignee(Agent $agent): TaskCollection;

    /**
     * Find ready tasks (no blockers, open status)
     *
     * @param  int  $limit  Maximum number of tasks to return
     */
    public function findReady(int $limit = 10): TaskCollection;

    /**
     * Find all tasks
     */
    public function findAll(): TaskCollection;

    /**
     * Get comments for a task
     */
    public function getComments(TaskId $taskId): CommentCollection;

    /**
     * Save task (create or update)
     *
     * Note: This is a high-level operation. Actual implementation
     * may use bd CLI commands (create, update, close, etc.)
     */
    public function save(Task $task): void;

    /**
     * Delete task
     */
    public function delete(TaskId $id): void;
}

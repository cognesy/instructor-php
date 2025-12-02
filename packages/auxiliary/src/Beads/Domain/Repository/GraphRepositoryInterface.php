<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Repository;

use Cognesy\Auxiliary\Beads\Domain\Collection\TaskCollection;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;

/**
 * Graph Repository Interface
 *
 * Pure domain contract for graph analysis operations (powered by bv).
 * Provides access to dependency analysis, priority recommendations,
 * and execution planning.
 */
interface GraphRepositoryInterface
{
    /**
     * Get tasks that block the specified task
     *
     * @return TaskCollection Tasks blocking this task (dependencies)
     */
    public function getBlockedBy(TaskId $taskId): TaskCollection;

    /**
     * Get tasks blocked by the specified task
     *
     * @return TaskCollection Tasks this task blocks
     */
    public function getBlocking(TaskId $taskId): TaskCollection;

    /**
     * Get all blocked tasks in the project
     *
     * @return TaskCollection All tasks with unresolved blockers
     */
    public function getAllBlocked(): TaskCollection;

    /**
     * Get dependency insights (graph metrics)
     *
     * Returns metrics like PageRank, betweenness centrality, HITS,
     * critical path analysis, and cycle detection.
     *
     * @return array<mixed>
     */
    public function getInsights(): array;

    /**
     * Get execution plan (parallel tracks)
     *
     * Returns an execution plan with parallel tracks showing
     * which tasks can be worked on simultaneously.
     *
     * @return array<mixed>
     */
    public function getExecutionPlan(): array;

    /**
     * Get priority recommendations for tasks
     *
     * Returns AI-powered priority recommendations with reasoning.
     *
     * @return array<mixed>
     */
    public function getPriorityRecommendations(): array;

    /**
     * Get dependency tree for a task
     *
     * Returns full dependency tree (recursive) for visualization
     * or deep analysis.
     *
     * @return array{
     *     task_id: string,
     *     blockers: array<mixed>,
     *     blocking: array<mixed>,
     *     depth: int
     * }
     */
    public function getDependencyTree(TaskId $taskId): array;
}

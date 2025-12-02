<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Service;

use Cognesy\Auxiliary\Beads\Domain\Collection\TaskCollection;
use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Repository\GraphRepositoryInterface;

/**
 * Dependency Service
 *
 * Domain service for analyzing and managing task dependencies.
 * Uses GraphRepository to access dependency graph data.
 */
final readonly class DependencyService
{
    public function __construct(
        private GraphRepositoryInterface $graphRepository,
    ) {}

    /**
     * Check if task has unresolved blockers
     */
    public function hasBlockers(Task $task): bool
    {
        $blockers = $this->graphRepository->getBlockedBy($task->id());

        return $blockers
            ->filter(fn (Task $blocker) => ! $blocker->isClosed())
            ->isNotEmpty();
    }

    /**
     * Check if task can be started (no blockers)
     */
    public function canStart(Task $task): bool
    {
        return ! $this->hasBlockers($task);
    }

    /**
     * Get unresolved blockers for task
     */
    public function getUnresolvedBlockers(Task $task): TaskCollection
    {
        return $this->graphRepository
            ->getBlockedBy($task->id())
            ->filter(fn (Task $blocker) => ! $blocker->isClosed());
    }

    /**
     * Get tasks that will be unblocked if this task closes
     */
    public function getWillUnblock(Task $task): TaskCollection
    {
        $blocking = $this->graphRepository->getBlocking($task->id());

        // Filter to tasks that only have this one blocker remaining
        return $blocking->filter(function (Task $blocked) use ($task) {
            $blockers = $this->graphRepository->getBlockedBy($blocked->id());
            $unresolvedBlockers = $blockers->filter(fn (Task $b) => ! $b->isClosed());

            // Only include if this is the last blocker
            return $unresolvedBlockers->count() === 1
                && $unresolvedBlockers->first()?->id()->equals($task->id());
        });
    }

    /**
     * Detect circular dependencies
     *
     * @return array<array<string>> Array of cycles, each cycle is array of task IDs
     */
    public function detectCycles(): array
    {
        $insights = $this->graphRepository->getInsights();

        return $insights['cycles'];
    }

    /**
     * Check if adding a dependency would create a cycle
     */
    public function wouldCreateCycle(Task $from, Task $to): bool
    {
        // This would require checking if 'to' already depends on 'from' (directly or transitively)
        // For now, return false (defer complex cycle detection to infrastructure)
        // Real implementation would use graph traversal
        return false;
    }
}

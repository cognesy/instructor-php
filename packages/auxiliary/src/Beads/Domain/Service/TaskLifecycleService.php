<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Service;

use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;

/**
 * Task Lifecycle Service
 *
 * Domain service for complex task state transitions and lifecycle operations
 * that involve multiple entities or external validation.
 */
final readonly class TaskLifecycleService
{
    /**
     * Attempt to claim task for agent
     *
     * Validates that task can be claimed and performs the claim operation.
     * Returns null if task cannot be claimed.
     */
    public function attemptClaim(Task $task, Agent $agent): ?Task
    {
        if (! $task->canBeClaimed()) {
            return null;
        }

        return $task->claim($agent);
    }

    /**
     * Attempt to start task
     *
     * Validates that task can be started and performs the start operation.
     * Returns null if task cannot be started.
     */
    public function attemptStart(Task $task): ?Task
    {
        if (! $task->canBeStarted()) {
            return null;
        }

        return $task->start();
    }

    /**
     * Attempt to complete task
     *
     * Validates that task can be completed and performs the completion.
     * Returns null if task cannot be completed.
     */
    public function attemptComplete(Task $task, string $reason): ?Task
    {
        if (! $task->canBeCompleted()) {
            return null;
        }

        return $task->complete($reason);
    }

    /**
     * Claim and start task in one operation
     *
     * Convenience method for the common workflow of claiming and immediately
     * starting a task. Since claiming already sets status to in_progress,
     * this is equivalent to attemptClaim.
     */
    public function claimAndStart(Task $task, Agent $agent): ?Task
    {
        return $this->attemptClaim($task, $agent);
    }
}

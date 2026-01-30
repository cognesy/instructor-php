<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Guards\Contracts;

use DateTimeImmutable;

/**
 * Contract for guard hooks that need to know when execution starts.
 *
 * Implement this interface for time-based guards or any guard that needs
 * to track state across the execution lifecycle.
 */
interface CanStartExecution
{
    /**
     * Signal that execution has started.
     *
     * @param DateTimeImmutable $startedAt When execution began
     */
    public function executionStarted(DateTimeImmutable $startedAt): void;
}

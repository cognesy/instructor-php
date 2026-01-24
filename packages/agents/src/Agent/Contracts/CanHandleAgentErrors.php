<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Contracts;

use Cognesy\Agents\Agent\Data\AgentState;
use Throwable;

/**
 * Handles errors that occur during agent execution.
 *
 * Implementations decide how to handle errors (stop, retry, ignore)
 * and return the appropriate state after handling.
 */
interface CanHandleAgentErrors
{
    /**
     * Handle an error that occurred during agent execution.
     *
     * Returns the state after handling. The returned state may include:
     * - A recorded failure step
     * - Updated status (e.g., Failed)
     * - Modified continuation outcome
     *
     * @param Throwable $error The error that occurred
     * @param AgentState $state The state at the time of the error
     * @return AgentState The state after handling the error
     */
    public function handleError(Throwable $error, AgentState $state): AgentState;
}

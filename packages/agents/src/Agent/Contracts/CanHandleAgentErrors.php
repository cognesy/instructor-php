<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Contracts;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\ErrorHandling\ErrorHandlingResult;
use Throwable;

/**
 * Handles errors that occur during agent execution.
 *
 * Implementations decide how to handle errors (stop, retry, ignore)
 * and return a structured handling outcome.
 */
interface CanHandleAgentErrors
{
    /**
     * Handle an error that occurred during agent execution.
     *
     * @param Throwable $error The error that occurred
     * @param AgentState $state The state at the time of the error
     * @return ErrorHandlingResult Structured error-handling outcome
     */
    public function handleError(Throwable $error, AgentState $state): ErrorHandlingResult;
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\PipelineAgent\Handlers;

use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\PipelineAgent\ExecutionContext;
use Cognesy\Agents\PipelineAgent\PhaseHandler;

/**
 * Core handler that determines final execution status.
 *
 * This handler runs in the AfterExecution phase to set the final status.
 */
final class FinalizeExecutionHandler implements PhaseHandler
{
    #[\Override]
    public function handle(AgentState $state, ExecutionContext $ctx): AgentState
    {
        $status = match ($state->stopReason()) {
            StopReason::ErrorForbade => AgentStatus::Failed,
            default => AgentStatus::Completed,
        };

        return $state->withStatus($status);
    }
}

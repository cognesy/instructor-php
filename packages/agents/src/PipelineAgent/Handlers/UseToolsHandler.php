<?php declare(strict_types=1);

namespace Cognesy\Agents\PipelineAgent\Handlers;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\CurrentExecution;
use Cognesy\Agents\PipelineAgent\ExecutionContext;
use Cognesy\Agents\PipelineAgent\PhaseHandler;
use DateTimeImmutable;

/**
 * Core handler that executes the driver's useTools method.
 *
 * This is the essential handler for the ExecuteStep phase.
 */
final class UseToolsHandler implements PhaseHandler
{
    #[\Override]
    public function handle(AgentState $state, ExecutionContext $ctx): AgentState
    {
        // Update execution timing
        $currentExecution = $state->currentExecution();
        if ($currentExecution !== null) {
            $state = $state->withCurrentExecution(new CurrentExecution(
                stepNumber: $currentExecution->stepNumber,
                startedAt: new DateTimeImmutable(),
                id: $currentExecution->id,
            ));
        }

        // Execute tools via driver
        $rawStep = $ctx->driver->useTools(
            state: $state,
            tools: $ctx->tools,
            executor: $ctx->toolExecutor,
        );

        return $state->recordStep($rawStep);
    }
}

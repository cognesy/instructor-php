<?php declare(strict_types=1);

namespace Cognesy\Agents\PipelineAgent;

use Cognesy\Agents\Core\Data\AgentState;

/**
 * A handler that processes agent state during a specific execution phase.
 *
 * Handlers are composed into pipelines and executed in order.
 * Each handler transforms the state and passes it to the next handler.
 */
interface PhaseHandler
{
    /**
     * Process the agent state and return the transformed state.
     */
    public function handle(AgentState $state, ExecutionContext $ctx): AgentState;
}

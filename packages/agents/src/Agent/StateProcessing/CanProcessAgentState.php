<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing;

use Cognesy\Agents\Agent\Data\AgentState;

/**
 * Contract for processing AgentState.
 */
interface CanProcessAgentState
{
    /**
     * Determine if this processor should run for the given state.
     */
    public function canProcess(AgentState $state): bool;

    /**
     * Process the state and optionally pass to the next processor.
     *
     * @param (callable(AgentState): AgentState)|null $next
     */
    public function process(AgentState $state, ?callable $next = null): AgentState;
}

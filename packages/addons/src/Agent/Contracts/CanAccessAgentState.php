<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Contracts;

use Cognesy\Addons\Agent\Data\AgentState;

/**
 * Interface for tools that need access to the current agent execution state.
 *
 * Tools implementing this interface will receive the current AgentState
 * before invocation via the withAgentState() method.
 *
 * State is read-only - tools should not modify it directly.
 * State modifications should be handled by the agent's state processors.
 */
interface CanAccessAgentState
{
    /**
     * Inject the current agent execution state.
     * Returns a new instance with the state injected.
     */
    public function withAgentState(AgentState $state): static;
}

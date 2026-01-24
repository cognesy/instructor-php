<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Contracts;

use Cognesy\Agents\Agent\Data\AgentState;

/**
 * Defines the execution loop for agents.
 *
 * This interface provides two ways to execute the agent:
 * - execute(): Run to completion and return final state
 * - iterate(): Step through execution, yielding each state
 */
interface CanControlAgentLoop
{
    /**
     * Perform all steps and return the final state.
     */
    public function execute(AgentState $state): AgentState;

    /**
     * Create an iterator to traverse through each step.
     *
     * Yields the state after each step, allowing callers to observe
     * or react to intermediate states during execution.
     *
     * @return iterable<AgentState>
     */
    public function iterate(AgentState $state): iterable;
}

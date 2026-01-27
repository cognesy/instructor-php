<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Contracts;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;

/**
 * An interface for generating the next step in the agent execution process.
 */
interface CanMakeNextStep
{
    /**
     * Generate the next step based on the provided state.
     *
     * @param AgentState $state The current state of the agent.
     * @return AgentStep The resulting step generated from the state.
     */
    public function makeNextStep(AgentState $state): AgentStep;
}

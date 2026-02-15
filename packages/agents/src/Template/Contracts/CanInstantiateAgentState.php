<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Contracts;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Template\Data\AgentDefinition;

interface CanInstantiateAgentState
{
    public function instantiateAgentState(
        AgentDefinition $definition,
        ?AgentState $seed = null,
    ): AgentState;
}

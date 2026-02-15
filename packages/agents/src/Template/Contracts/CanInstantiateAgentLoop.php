<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Contracts;

use Cognesy\Agents\CanControlAgentLoop;
use Cognesy\Agents\Template\Data\AgentDefinition;

interface CanInstantiateAgentLoop
{
    public function instantiateAgentLoop(AgentDefinition $definition): CanControlAgentLoop;
}

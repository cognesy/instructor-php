<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentTemplate\Contracts;

use Cognesy\Agents\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;

interface AgentBlueprint
{
    public static function fromDefinition(AgentDefinition $definition): AgentInterface;
}

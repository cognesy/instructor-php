<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentTemplate\Contracts;

use Cognesy\Addons\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Addons\AgentTemplate\Definitions\AgentDefinition;

interface AgentBlueprint
{
    public static function fromDefinition(AgentDefinition $definition): AgentInterface;
}

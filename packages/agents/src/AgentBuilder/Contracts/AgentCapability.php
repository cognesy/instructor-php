<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Contracts;

use Cognesy\Agents\AgentBuilder\AgentBuilder;

interface AgentCapability
{
    public function install(AgentBuilder $builder): void;
}

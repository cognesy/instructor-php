<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentBuilder\Contracts;

use Cognesy\Addons\AgentBuilder\AgentBuilder;

interface AgentCapability
{
    public function install(AgentBuilder $builder): void;
}

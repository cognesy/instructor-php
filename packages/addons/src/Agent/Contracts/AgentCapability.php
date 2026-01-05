<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Contracts;

use Cognesy\Addons\Agent\AgentBuilder;

interface AgentCapability
{
    public function install(AgentBuilder $builder): void;
}

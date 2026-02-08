<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Tasks;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Agents\Hooks\Collections\HookTriggers;

final readonly class UseTaskPlanning implements AgentCapability
{
    #[\Override]
    public function install(AgentBuilder $builder): void
    {
        $builder->withTools([new TodoWriteTool()]);
        $builder->addHook(new PersistTasksHook(), HookTriggers::afterStep(), -50);
    }
}

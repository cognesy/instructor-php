<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Tasks;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Hook\Collections\HookTriggers;

final readonly class UseTaskPlanning implements CanProvideAgentCapability
{
    #[\Override]
    public static function capabilityName(): string {
        return 'use_task_planning';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent
    {
        $agent = $agent->withTools(
            $agent->tools()->merge(new Tools(new TodoWriteTool()))
        );
        $hooks = $agent->hooks()->with(
            hook: new PersistTasksHook(),
            triggerTypes: HookTriggers::afterStep(),
            priority: -50,
        );
        return $agent->withHooks($hooks);
    }
}

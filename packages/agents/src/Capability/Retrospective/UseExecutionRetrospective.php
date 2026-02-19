<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrospective;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Enums\HookTrigger;

final class UseExecutionRetrospective implements CanProvideAgentCapability
{
    public function __construct(
        private ?RetrospectivePolicy $policy = null,
        private ?\Closure $onRewind = null,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_execution_retrospective';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $policy = $this->policy ?? new RetrospectivePolicy();

        // Register the tool
        $tools = $agent->tools()->merge(
            new Tools(new ExecutionRetrospectiveTool())
        );

        // Register the hook for BeforeExecution (system prompt), BeforeStep (checkpoints), AfterStep (rewind)
        $hooks = $agent->hooks()->with(
            hook: new ExecutionRetrospectiveHook(policy: $policy, onRewind: $this->onRewind),
            triggerTypes: HookTriggers::with(HookTrigger::BeforeExecution, HookTrigger::BeforeStep, HookTrigger::AfterStep),
            priority: 100,
            name: 'execution_retrospective',
        );

        return $agent->withTools($tools)->withHooks($hooks);
    }
}

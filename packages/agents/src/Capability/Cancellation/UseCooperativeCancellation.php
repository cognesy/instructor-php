<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Cancellation;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Override;

final readonly class UseCooperativeCancellation implements CanProvideAgentCapability
{
    public function __construct(
        private CanProvideCancellationSignal $source,
    ) {}

    #[Override]
    public static function capabilityName(): string {
        return 'use_cooperative_cancellation';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $hooks = $agent->hooks()->with(
            hook: new CooperativeCancellationHook($this->source),
            triggerTypes: HookTriggers::of(HookTrigger::BeforeExecution, HookTrigger::BeforeStep),
            priority: 250,
            name: 'guard:cooperative_cancellation',
        );

        return $agent->withHooks($hooks);
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\SelfCritique;

use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;

class UseSelfCritique implements CanProvideAgentCapability
{
    public function __construct(
        private CanCreateStructuredOutput $structuredOutput,
        private int $maxIterations = 2,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_self_critique';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $hooks = $agent->hooks()->with(
            hook: new SelfCriticHook(
                maxCriticIterations: $this->maxIterations,
                structuredOutput: $this->structuredOutput,
            ),
            triggerTypes: HookTriggers::afterStep(),
        );
        return $agent->withHooks($hooks);
    }
}

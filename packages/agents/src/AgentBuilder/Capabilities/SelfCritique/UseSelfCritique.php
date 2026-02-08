<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\SelfCritique;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Agents\Hooks\Collections\HookTriggers;

class UseSelfCritique implements AgentCapability
{
    public function __construct(
        private int $maxIterations = 2,
        private ?string $llmPreset = null,
    ) {}

    #[\Override]
    public function install(AgentBuilder $builder): void {
        $builder->addHook(new SelfCriticHook(
            maxCriticIterations: $this->maxIterations,
            llmPreset: $this->llmPreset,
        ), HookTriggers::afterStep());
    }
}

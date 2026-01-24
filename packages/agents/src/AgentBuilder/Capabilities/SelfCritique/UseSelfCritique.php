<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\SelfCritique;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;

class UseSelfCritique implements AgentCapability
{
    public function __construct(
        private int $maxIterations = 2,
        private bool $verbose = true,
        private ?string $llmPreset = null,
    ) {}

    #[\Override]
    public function install(AgentBuilder $builder): void {
        $builder->addProcessor(new SelfCriticProcessor(
            maxIterations: $this->maxIterations,
            verbose: $this->verbose,
            llmPreset: $this->llmPreset,
        ));

        // Self-critic wanting a revision is a valid reason to continue the loop
        // (combined with other continue signals using OR logic)
        $builder->addContinuationCriteria(new SelfCriticContinuationCheck(
            maxIterations: $this->maxIterations,
        ));
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Extras\SelfCritique\SelfCriticContinuationCheck;
use Cognesy\Addons\Agent\Extras\SelfCritique\SelfCriticProcessor;

class UseSelfCritique implements AgentCapability
{
    public function __construct(
        private int $maxIterations = 2,
        private bool $verbose = true,
        private ?string $llmPreset = null,
    ) {}

    public function install(AgentBuilder $builder): void {
        $builder->addProcessor(new SelfCriticProcessor(
            maxIterations: $this->maxIterations,
            verbose: $this->verbose,
            llmPreset: $this->llmPreset,
        ));

        $builder->addContinuationCriteria(new SelfCriticContinuationCheck(
            maxIterations: $this->maxIterations,
        ));
    }
}

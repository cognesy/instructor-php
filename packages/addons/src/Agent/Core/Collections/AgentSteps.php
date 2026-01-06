<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Collections;

use Cognesy\Addons\StepByStep\Collections\Steps;
use Cognesy\Addons\Agent\Data\AgentStep;

/** @extends Steps<AgentStep> */
final readonly class AgentSteps extends Steps
{
    public function __construct(AgentStep ...$steps) {
        parent::__construct(...$steps);
    }

    public static function fromArray(array $data): self {
        $steps = array_map(fn($stepData) => AgentStep::fromArray($stepData), $data);
        return new self(...$steps);
    }

    public function toArray(): array {
        return array_map(fn(AgentStep $step) => $step->toArray(), $this->all());
    }

    #[\Override]
    public function currentStep(): ?AgentStep {
        /** @var ?AgentStep */
        return parent::currentStep();
    }

    #[\Override]
    public function stepAt(int $index): ?AgentStep {
        /** @var ?AgentStep */
        return parent::stepAt($index);
    }

}

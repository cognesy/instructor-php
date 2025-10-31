<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Collections;

use Cognesy\Addons\Collaboration\Data\CollaborationStep;
use Cognesy\Addons\StepByStep\Collections\Steps;

/** @extends Steps<CollaborationStep> */
final readonly class CollaborationSteps extends Steps
{
    public function __construct(CollaborationStep ...$steps) {
        parent::__construct(...$steps);
    }

    public static function fromArray(array $data): self {
        $steps = array_map(fn(array $stepData) => CollaborationStep::fromArray($stepData), $data);
        return new self(...$steps);
    }

    public function toArray(): array {
        return array_map(fn(CollaborationStep $step) => $step->toArray(), $this->all());
    }

    #[\Override]
    public function currentStep(): ?CollaborationStep {
        /** @var ?CollaborationStep */
        return parent::currentStep();
    }

    #[\Override]
    public function stepAt(int $index): ?CollaborationStep {
        /** @var ?CollaborationStep */
        return parent::stepAt($index);
    }

}

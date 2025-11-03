<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\State;

use Cognesy\Addons\Collaboration\Collections\CollaborationSteps;
use Cognesy\Addons\Collaboration\Data\CollaborationStep;

trait HandlesCollaborationSteps
{
    protected readonly CollaborationSteps $steps;
    protected readonly ?CollaborationStep $currentStep;

    // MUTATORS /////////////////////////////////////////////////

    /**
     * @param CollaborationStep $step
     */
    public function withAddedStep(object $step): static {
        assert($step instanceof CollaborationStep);
        return $this->with(steps: $this->steps->withAddedSteps($step));
    }

    /**
     * @param CollaborationStep ...$step
     */
    public function withAddedSteps(object ...$step): static {
        foreach ($step as $s) {
            assert($s instanceof CollaborationStep);
        }
        return $this->with(steps: $this->steps->withAddedSteps(...$step));
    }

    public function withCurrentStep(object $step): static {
        assert($step instanceof CollaborationStep);
        return $this->with(currentStep: $step);
    }

    // ACCESSORS /////////////////////////////////////////////////

    public function currentStep(): ?CollaborationStep {
        return $this->currentStep;
    }

    public function steps(): CollaborationSteps {
        return $this->steps;
    }

    public function stepCount(): int {
        return $this->steps->count();
    }

    public function stepAt(int $index): ?CollaborationStep {
        return $this->steps->stepAt($index);
    }

    /** @return iterable<CollaborationStep> */
    public function eachStep(): iterable {
        return $this->steps;
    }
}
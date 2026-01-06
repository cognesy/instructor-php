<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\State;

use Cognesy\Addons\Agent\Collections\AgentSteps;
use Cognesy\Addons\Agent\Data\AgentStep;

trait HandlesAgentSteps
{
    protected readonly AgentSteps $steps;
    protected readonly ?AgentStep $currentStep;

    // MUTATORS /////////////////////////////////////////////////

    public function withCurrentStep(object $step) : static {
        assert($step instanceof AgentStep);
        return $this->with(currentStep: $step);
    }

    /**
     * @param AgentStep $step
     */
    public function withAddedStep(object $step) : static {
        assert($step instanceof AgentStep);
        /** @var AgentStep $step */
        return $this->with(steps: $this->steps->withAddedSteps($step));
    }

    /**
     * @param AgentStep ...$step
     */
    public function withAddedSteps(object ...$step): static {
        return $this->with(steps: $this->steps->withAddedSteps(...$step));
    }

    // ACCESSORS /////////////////////////////////////////////////

    public function currentStep() : ?AgentStep {
        return $this->currentStep;
    }

    public function steps() : AgentSteps {
        return $this->steps;
    }

    public function stepCount() : int {
        return $this->steps->count();
    }

    public function stepAt(int $index): ?AgentStep {
        return $this->steps->stepAt($index);
    }

    /** @return iterable<AgentStep> */
    public function eachStep(): iterable {
        return $this->steps;
    }
}
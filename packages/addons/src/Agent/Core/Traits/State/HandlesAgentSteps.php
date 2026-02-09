<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Core\Traits\State;

use Cognesy\Addons\Agent\Core\Collections\AgentSteps;
use Cognesy\Addons\Agent\Core\Data\AgentStep;

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
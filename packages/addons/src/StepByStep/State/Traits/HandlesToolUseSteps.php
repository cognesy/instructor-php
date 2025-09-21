<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State\Traits;

use Cognesy\Addons\ToolUse\Collections\ToolUseSteps;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;

trait HandlesToolUseSteps
{
    protected readonly ToolUseSteps $steps;
    protected readonly ?ToolUseStep $currentStep;

    // MUTATORS /////////////////////////////////////////////////

    public function withCurrentStep(ToolUseStep $step) : static {
        return $this->with(currentStep: $step);
    }

    /**
     * @param object<ToolUseStep> $step
     */
    public function withAddedStep(object $step) : static {
        assert($step instanceof ToolUseStep);
        return $this->with(steps: $this->steps->withAddedSteps($step));
    }

    /**
     * @param object<ToolUseStep> ...$step
     */
    public function withAddedSteps(object ...$step): static {
        return $this->with(steps: $this->steps->withAddedSteps(...$step));
    }

    // ACCESSORS /////////////////////////////////////////////////

    public function currentStep() : ?ToolUseStep {
        return $this->currentStep;
    }

    public function steps() : ToolUseSteps {
        return $this->steps;
    }

    public function stepCount() : int {
        return $this->steps->count();
    }

    public function stepAt(int $index): ?ToolUseStep {
        return $this->steps->stepAt($index);
    }

    /** @return iterable<ToolUseStep> */
    public function eachStep(): iterable {
        return $this->steps;
    }
}
<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\State\Traits;

use Cognesy\Addons\Chat\Collections\ChatSteps;
use Cognesy\Addons\Chat\Data\ChatStep;

trait HandlesChatSteps
{
    protected readonly ChatSteps $steps;
    protected readonly ?ChatStep $currentStep;

    // MUTATORS /////////////////////////////////////////////////

    /**
     * @param object<ChatStep> $step
     */
    public function withAddedStep(object $step): static {
        assert($step instanceof ChatStep);
        return $this->with(steps: $this->steps->withAddedSteps($step));
    }

    /**
     * @param object<ChatStep> ...$step
     */
    public function withAddedSteps(object ...$step): static {
        return $this->with(steps: $this->steps->withAddedSteps(...$step));
    }

    public function withCurrentStep(ChatStep $step): self {
        return $this->with(currentStep: $step);
    }

    // ACCESSORS /////////////////////////////////////////////////

    public function currentStep(): ?ChatStep {
        return $this->currentStep;
    }

    public function steps(): ChatSteps {
        return $this->steps;
    }

    public function stepCount(): int {
        return $this->steps->count();
    }

    public function stepAt(int $index): ?ChatStep {
        return $this->steps->stepAt($index);
    }

    /** @return iterable<ChatStep> */
    public function eachStep(): iterable {
        return $this->steps;
    }
}
<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\State;

use Cognesy\Addons\StepByStep\Collections\Steps as GenericSteps;
use Cognesy\Addons\StepByStep\State\Contracts\HasMessageStore;
use Cognesy\Addons\StepByStep\State\Contracts\HasStateInfo;
use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Cognesy\Addons\StepByStep\State\Contracts\HasUsage;
use Cognesy\Addons\StepByStep\State\StateInfo;
use Cognesy\Addons\StepByStep\State\Traits\HandlesMessageStore;
use Cognesy\Addons\StepByStep\State\Traits\HandlesStateInfo;
use Cognesy\Addons\StepByStep\State\Traits\HandlesUsage;
use Cognesy\Experimental\RLM\Data\Handles\ResultHandle;
use Cognesy\Experimental\RLM\Data\Policy;
use Cognesy\Experimental\RLM\Steps\RlmStep;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\Usage;

/** @implements HasSteps<RlmStep> */
final readonly class RlmState implements HasSteps, HasMessageStore, HasUsage, HasStateInfo
{
    use HandlesMessageStore;
    use HandlesStateInfo;
    use HandlesUsage;

    private GenericSteps $steps;
    private ?RlmStep $currentStep;
    private bool $terminal;
    private ?ResultHandle $finalHandle;

    public function __construct(
        private Policy $policy,
        ?GenericSteps $steps = null,
        ?RlmStep $currentStep = null,
        ?Usage $usage = null,
        ?MessageStore $store = null,
        ?StateInfo $stateInfo = null,
        bool $terminal = false,
        ?ResultHandle $finalHandle = null,
    ) {
        $this->steps = $steps ?? new GenericSteps();
        $this->currentStep = $currentStep;
        $this->usage = $usage ?? new Usage();
        $this->store = $store ?? new MessageStore();
        $this->stateInfo = $stateInfo ?? StateInfo::new();
        $this->terminal = $terminal;
        $this->finalHandle = $finalHandle;
    }

    public static function start(Policy $policy): self {
        return new self($policy);
    }

    public function with(
        ?Policy $policy = null,
        ?GenericSteps $steps = null,
        ?RlmStep $currentStep = null,
        ?Usage $usage = null,
        ?MessageStore $store = null,
        ?StateInfo $stateInfo = null,
        ?bool $terminal = null,
        ?ResultHandle $finalHandle = null,
    ): static {
        return new static(
            policy: $policy ?? $this->policy,
            steps: $steps ?? $this->steps,
            currentStep: $currentStep ?? $this->currentStep,
            usage: $usage ?? $this->usage,
            store: $store ?? $this->store,
            stateInfo: $stateInfo ?? $this->stateInfo->touch(),
            terminal: $terminal ?? $this->terminal,
            finalHandle: $finalHandle ?? $this->finalHandle,
        );
    }

    // HasSteps ///////////////////////////////////////////////////

    public function currentStep(): ?object {
        return $this->currentStep;
    }

    public function stepCount(): int {
        return $this->steps->count();
    }

    public function stepAt(int $index): ?object {
        return $this->steps->stepAt($index);
    }

    public function eachStep(): iterable {
        return $this->steps;
    }

    public function withAddedStep(object $step): static {
        return $this->with(steps: $this->steps->withAddedStep($step));
    }

    public function withAddedSteps(object ...$step): static {
        return $this->with(steps: $this->steps->withAddedSteps(...$step));
    }

    public function withCurrentStep(object $step): static {
        return $this->with(currentStep: $step);
    }

    // Accessors /////////////////////////////////////////////////

    public function policy(): Policy {
        return $this->policy;
    }

    public function isTerminal(): bool {
        return $this->terminal;
    }

    public function finalHandle(): ?ResultHandle {
        return $this->finalHandle;
    }
}


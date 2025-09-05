<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

use Cognesy\Addons\Chat\Contracts\CanProcessChatStep;

final class StepProcessors
{
    /** @var CanProcessChatStep[] */
    private array $items = [];

    public function add(CanProcessChatStep ...$processors) : self {
        foreach ($processors as $processor) { $this->items[] = $processor; }
        return $this;
    }

    public function isEmpty() : bool { return $this->items === []; }

    public function apply(ChatStep $step, ChatState $state) : ChatStepOutcome {
        $currentStep = $step;
        $currentState = $state;
        foreach ($this->items as $processor) {
            $outcome = $processor->processStep($currentStep, $currentState);
            $currentStep = $outcome->step();
            $currentState = $outcome->state();
        }
        return new ChatStepOutcome($currentStep, $currentState);
    }
}

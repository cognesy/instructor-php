<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing\Processors;

use Cognesy\Addons\StepByStep\State\Contracts\HasMessageStore;
use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepMessages;

/**
 * @implements CanProcessAnyState<HasSteps<object>&HasMessageStore>
 */
final class AppendStepMessages implements CanProcessAnyState
{
    #[\Override]
    public function canProcess(object $state): bool {
        return $state instanceof HasSteps
            && $state instanceof HasMessageStore;
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object {
        $newState = $next ? $next($state) : $state;

        assert($newState instanceof HasSteps);
        assert($newState instanceof HasMessageStore);

        $currentStep = $newState->currentStep();

        if ($currentStep === null) {
            /** @var HasSteps<object>&HasMessageStore $newState */
            return $newState;
        }

        // Only append the output message from the step, not all messages
        // This prevents duplication of input messages that are already in the state
        if (!($currentStep instanceof HasStepMessages)) {
            /** @var HasSteps<object>&HasMessageStore $newState */
            return $newState;
        }

        $outputMessages = $currentStep->outputMessages();
        if ($outputMessages->isEmpty()) {
            /** @var HasSteps<object>&HasMessageStore $newState */
            return $newState;
        }

        $newMessages = $newState->messages()->appendMessages($outputMessages);
        /** @var HasSteps<object>&HasMessageStore $newState */
        return $newState->withMessages($newMessages);
    }
}

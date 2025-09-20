<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Processors;

use Cognesy\Addons\Core\Contracts\CanProcessAnyState;
use Cognesy\Addons\Core\StateContracts\HasMessageStore;
use Cognesy\Addons\Core\StateContracts\HasSteps;
use Cognesy\Addons\Core\StepContracts\HasStepMessages;

final class AppendStepMessages implements CanProcessAnyState
{
    public function canProcess(object $state): bool {
        return $state instanceof HasSteps
            && $state instanceof HasMessageStore;
    }

    public function process(object $state, ?callable $next = null): object {
        assert($state instanceof HasSteps);
        $currentStep = $state->currentStep();

        if ($currentStep === null) {
            return $next ? $next($state) : $state;
        }

        assert($state instanceof HasMessageStore);

        // Only append the output message from the step, not all messages
        // This prevents duplication of input messages that are already in the state
        if (!($currentStep instanceof HasStepMessages)) {
            return $next ? $next($state) : $state;
        }

        $outputMessages = $currentStep->outputMessages();
        if ($outputMessages->isEmpty()) {
            return $next ? $next($state) : $state;
        }

        $newMessages = $state->messages()->appendMessages($outputMessages);
        $newState = $state->withMessages($newMessages);

        return $next ? $next($newState) : $newState;
    }
}

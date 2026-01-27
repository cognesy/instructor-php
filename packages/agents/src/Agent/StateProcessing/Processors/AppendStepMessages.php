<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing\Processors;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;

final class AppendStepMessages implements CanProcessAgentState
{
    #[\Override]
    public function canProcess(AgentState $state): bool {
        return true;
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next ? $next($state) : $state;

        $currentStep = $newState->currentStep();

        // Only append the output message from the step, not all messages
        // This prevents duplication of input messages that are already in the state
        if ($currentStep === null) {
            return $newState;
        }

        $outputMessages = $currentStep->outputMessages();
        if ($outputMessages->isEmpty()) {
            return $newState;
        }

        return $newState->withMessages(
            $newState->messages()->appendMessages($outputMessages)
        );
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing\Processors;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

final class AppendFinalResponse implements CanProcessAgentState
{
    #[\Override]
    public function canProcess(AgentState $state): bool {
        return true;
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next ? $next($state) : $state;
        $currentStep = $newState->currentStep();
        if ($currentStep === null) {
            return $newState;
        }
        if ($currentStep->hasToolCalls()) {
            return $newState;
        }

        $finalResponse = $this->extractFinalResponse($currentStep->outputMessages());
        if ($finalResponse === null) {
            return $newState;
        }

        return $newState->withMessages(
            $newState->messages()->appendMessage($finalResponse)
        );
    }

    private function extractFinalResponse(Messages $messages): ?Message {
        foreach ($messages->reversed()->each() as $message) {
            if (!$message->isAssistant()) {
                continue;
            }
            if ($message->metadata()->hasKey('tool_calls')) {
                continue;
            }
            if ($message->content()->isEmpty()) {
                continue;
            }
            return $message;
        }

        return null;
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing\Processors;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

final class AppendToolTraceToBuffer implements CanProcessAgentState
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

        $toolTrace = $this->extractToolTrace($currentStep->outputMessages());
        if ($toolTrace->isEmpty()) {
            return $newState;
        }

        $store = $newState->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->appendMessages($toolTrace);

        return $newState->withMessageStore($store);
    }

    private function extractToolTrace(Messages $messages): Messages {
        return $messages->filter(fn(Message $message): bool => $this->isToolTrace($message));
    }

    private function isToolTrace(Message $message): bool {
        if ($message->isTool()) {
            return true;
        }
        return $this->isToolCallMessage($message);
    }

    private function isToolCallMessage(Message $message): bool {
        return $message->isAssistant()
            && $message->metadata()->hasKey('tool_calls');
    }
}

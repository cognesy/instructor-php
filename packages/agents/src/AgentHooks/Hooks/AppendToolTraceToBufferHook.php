<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;
use Cognesy\Agents\AgentHooks\Data\StepHookContext;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

/**
 * Hook that appends tool call/response messages to the execution buffer.
 *
 * This hook separates tool traces from the main conversation by storing them
 * in a dedicated buffer section. This allows for cleaner conversation history
 * while preserving the full tool execution trace.
 */
final readonly class AppendToolTraceToBufferHook implements Hook
{
    #[\Override]
    public function handle(HookContext $context, callable $next): HookOutcome
    {
        if (!$context instanceof StepHookContext || $context->eventType() !== HookType::AfterStep) {
            return $next($context);
        }

        $state = $context->state();
        $currentStep = $state->currentStep();

        if ($currentStep === null) {
            return $next($context);
        }

        $toolTrace = $this->extractToolTrace($currentStep->outputMessages());
        if ($toolTrace->isEmpty()) {
            return $next($context);
        }

        $store = $state->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->appendMessages($toolTrace);

        $newState = $state->withMessageStore($store);
        return $next($context->withState($newState));
    }

    private function extractToolTrace(Messages $messages): Messages
    {
        return $messages->filter(fn(Message $message): bool => $this->isToolTrace($message));
    }

    private function isToolTrace(Message $message): bool
    {
        if ($message->isTool()) {
            return true;
        }
        return $this->isToolCallMessage($message);
    }

    private function isToolCallMessage(Message $message): bool
    {
        return $message->isAssistant()
            && $message->metadata()->hasKey('tool_calls');
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;
use Cognesy\Agents\AgentHooks\Data\StepHookContext;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

/**
 * Hook that appends the final assistant response (non-tool-call) to the conversation.
 *
 * When a step completes without tool calls (i.e., the agent is providing a final response),
 * this hook extracts and appends the assistant's response message.
 */
final readonly class AppendFinalResponseHook implements Hook
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

        if ($currentStep->hasToolCalls()) {
            return $next($context);
        }

        $finalResponse = $this->extractFinalResponse($currentStep->outputMessages());
        if ($finalResponse === null) {
            return $next($context);
        }

        $newState = $state->withMessages(
            $state->messages()->appendMessage($finalResponse)
        );

        return $next($context->withState($newState));
    }

    private function extractFinalResponse(Messages $messages): ?Message
    {
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

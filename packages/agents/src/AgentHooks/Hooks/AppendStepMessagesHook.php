<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;
use Cognesy\Agents\AgentHooks\Data\StepHookContext;
use Cognesy\Agents\AgentHooks\Enums\HookType;

/**
 * Hook that appends the current step's output messages to the conversation.
 *
 * After each step completes, this hook takes the output messages from the step
 * and appends them to the agent state's message history.
 */
final readonly class AppendStepMessagesHook implements Hook
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

        $outputMessages = $currentStep->outputMessages();
        if ($outputMessages->isEmpty()) {
            return $next($context);
        }

        $newState = $state->withMessages(
            $state->messages()->appendMessages($outputMessages)
        );

        return $next($context->withState($newState));
    }
}

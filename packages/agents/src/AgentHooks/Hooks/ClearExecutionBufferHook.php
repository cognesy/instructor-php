<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;
use Cognesy\Agents\AgentHooks\Data\StepHookContext;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Hook that clears the execution buffer when the agent is about to stop.
 *
 * When continuation evaluation indicates the agent should stop (no more work),
 * this hook clears the temporary execution buffer to clean up state.
 */
final readonly class ClearExecutionBufferHook implements Hook
{
    #[\Override]
    public function handle(HookContext $context, callable $next): HookOutcome
    {
        if (!$context instanceof StepHookContext || $context->eventType() !== HookType::AfterStep) {
            return $next($context);
        }

        $state = $context->state();
        $outcome = $state->continuationOutcome();

        if ($outcome === null) {
            return $next($context);
        }

        if ($outcome->shouldContinue()) {
            return $next($context);
        }

        $store = $state->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->clear();

        $newState = $state->withMessageStore($store);
        return $next($context->withState($newState));
    }
}

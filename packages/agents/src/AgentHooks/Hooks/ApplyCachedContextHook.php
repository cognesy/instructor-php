<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;
use Cognesy\Agents\AgentHooks\Data\StepHookContext;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Polyglot\Inference\Data\CachedContext;

/**
 * Hook that applies cached context to the agent state before a step.
 *
 * This is typically used to inject system prompts, pre-loaded context,
 * or other cached data into the agent's message history.
 */
final readonly class ApplyCachedContextHook implements Hook
{
    public function __construct(
        private CachedContext $cachedContext,
        private bool $onlyWhenEmpty = true,
    ) {}

    #[\Override]
    public function handle(HookContext $context, callable $next): HookOutcome
    {
        if (!$context instanceof StepHookContext || $context->eventType() !== HookType::BeforeStep) {
            return $next($context);
        }

        $state = $context->state();

        if ($this->cachedContext->isEmpty()) {
            return $next($context);
        }

        if ($this->onlyWhenEmpty && !$state->cache()->isEmpty()) {
            return $next($context);
        }

        $newState = $state->withCachedContext($this->cachedContext);
        return $next($context->withState($newState));
    }
}

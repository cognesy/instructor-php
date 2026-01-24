<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Hooks\Hooks;

use Closure;
use Cognesy\Agents\Agent\Hooks\Contracts\Hook;
use Cognesy\Agents\Agent\Hooks\Contracts\HookContext;
use Cognesy\Agents\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Agents\Agent\Hooks\Data\HookOutcome;
use Cognesy\Agents\Agent\Hooks\Data\StopHookContext;
use Cognesy\Agents\Agent\Hooks\Enums\HookType;

/**
 * Hook for intercepting subagent stop decisions.
 *
 * Fired when a subagent is about to stop. Similar to StopHook but
 * specifically for subagent completion.
 *
 * The callback must return a HookOutcome:
 * - HookOutcome::proceed() to allow the stop
 * - HookOutcome::block($reason) to force continuation
 *
 * @example
 * // Log subagent completion
 * $hook = new SubagentStopHook(
 *     callback: function (StopHookContext $ctx): HookOutcome {
 *         $state = $ctx->state();
 *         $this->logger->info("Subagent completed", [
 *             'agentId' => $state->agentId,
 *             'parentId' => $state->parentAgentId,
 *             'steps' => $state->stepCount(),
 *         ]);
 *         return HookOutcome::proceed();
 *     },
 * );
 *
 * @example
 * // Force subagent continuation under certain conditions
 * $hook = new SubagentStopHook(
 *     callback: function (StopHookContext $ctx): HookOutcome {
 *         if ($this->subagentNeedsMoreWork($ctx->state())) {
 *             return HookOutcome::block('Subagent has incomplete tasks');
 *         }
 *         return HookOutcome::proceed();
 *     },
 * );
 */
final readonly class SubagentStopHook implements Hook
{
    /** @var Closure(StopHookContext): HookOutcome */
    private Closure $callback;

    /**
     * @param callable(StopHookContext): HookOutcome $callback
     * @param HookMatcher|null $matcher Optional matcher for conditional execution
     */
    public function __construct(
        callable $callback,
        private ?HookMatcher $matcher = null,
    ) {
        $this->callback = Closure::fromCallable($callback);
    }

    #[\Override]
    public function handle(HookContext $context, callable $next): HookOutcome
    {
        // Only process SubagentStop events
        if (!$context instanceof StopHookContext || $context->eventType() !== HookType::SubagentStop) {
            return $next($context);
        }

        // Skip if matcher doesn't match
        if ($this->matcher !== null && !$this->matcher->matches($context)) {
            return $next($context);
        }

        // Execute callback - must return HookOutcome
        $outcome = ($this->callback)($context);

        // If blocked or stopped, return immediately
        if ($outcome->isBlocked() || $outcome->isStopped()) {
            return $outcome;
        }

        // Pass along (with potentially modified context)
        $effectiveContext = $outcome->context() ?? $context;
        return $next($effectiveContext);
    }
}

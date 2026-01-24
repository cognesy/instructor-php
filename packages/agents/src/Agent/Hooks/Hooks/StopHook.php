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
 * Hook for intercepting agent stop decisions.
 *
 * Fired when the agent is about to stop (continuation criteria say stop).
 * This hook can override the stop decision to force continuation.
 *
 * The callback must return a HookOutcome:
 * - HookOutcome::proceed() to allow the stop
 * - HookOutcome::block($reason) to force continuation
 *
 * @example
 * // Force continuation if there's unfinished work
 * $hook = new StopHook(
 *     callback: function (StopHookContext $ctx): HookOutcome {
 *         if ($this->hasUnfinishedWork($ctx->state())) {
 *             return HookOutcome::block('Work remaining - forcing continuation');
 *         }
 *         return HookOutcome::proceed();
 *     },
 * );
 *
 * @example
 * // Log stop decisions
 * $hook = new StopHook(
 *     callback: function (StopHookContext $ctx): HookOutcome {
 *         $outcome = $ctx->continuationOutcome();
 *         $this->logger->info("Agent stopping", [
 *             'reason' => $outcome->stopReason()->value,
 *             'resolvedBy' => $outcome->resolvedBy(),
 *         ]);
 *         return HookOutcome::proceed();
 *     },
 * );
 */
final readonly class StopHook implements Hook
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
        // Only process Stop events (not SubagentStop)
        if (!$context instanceof StopHookContext || $context->eventType() !== HookType::Stop) {
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

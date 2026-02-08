<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Hooks\Hooks;

use Closure;
use Cognesy\Addons\Agent\Hooks\Contracts\Hook;
use Cognesy\Addons\Agent\Hooks\Contracts\HookContext;
use Cognesy\Addons\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Addons\Agent\Hooks\Data\HookEvent;
use Cognesy\Addons\Agent\Hooks\Data\HookOutcome;
use Cognesy\Addons\Agent\Hooks\Data\StopHookContext;

/**
 * Hook for intercepting subagent stop decisions.
 *
 * Fired when a subagent is about to stop. Similar to StopHook but
 * specifically for subagent completion.
 *
 * The callback can:
 * - Return HookOutcome::proceed() to allow the stop
 * - Return HookOutcome::block($reason) to force continuation
 *
 * @example
 * // Log subagent completion
 * $hook = new SubagentStopHook(
 *     callback: function (StopHookContext $ctx): void {
 *         $state = $ctx->state();
 *         $this->logger->info("Subagent completed", [
 *             'agentId' => $state->agentId,
 *             'parentId' => $state->parentAgentId,
 *             'steps' => $state->stepCount(),
 *         ]);
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
    private Closure $callback;

    /**
     * @param callable(StopHookContext): (HookOutcome|void) $callback
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
        if (!$context instanceof StopHookContext || $context->eventType() !== HookEvent::SubagentStop) {
            return $next($context);
        }

        // Skip if matcher doesn't match
        if ($this->matcher !== null && !$this->matcher->matches($context)) {
            return $next($context);
        }

        // Execute callback
        $result = ($this->callback)($context);

        // Handle HookOutcome directly
        if ($result instanceof HookOutcome) {
            // If blocked, return immediately (force continuation)
            if ($result->isBlocked()) {
                return $result;
            }
            // If stopped, return immediately (confirm stop)
            if ($result->isStopped()) {
                return $result;
            }
            // If proceed, pass along
            $effectiveContext = $result->context() ?? $context;
            return $next($effectiveContext);
        }

        // Anything else = proceed (allow stop)
        return $next($context);
    }
}

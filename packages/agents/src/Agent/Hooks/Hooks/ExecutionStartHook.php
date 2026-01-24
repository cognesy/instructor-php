<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Hooks\Hooks;

use Closure;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Hooks\Contracts\Hook;
use Cognesy\Agents\Agent\Hooks\Contracts\HookContext;
use Cognesy\Agents\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Agents\Agent\Hooks\Data\ExecutionHookContext;
use Cognesy\Agents\Agent\Hooks\Data\HookEvent;
use Cognesy\Agents\Agent\Hooks\Data\HookOutcome;

/**
 * Hook for intercepting agent execution start.
 *
 * Fired once when agent.run() begins, before any steps are executed.
 *
 * The callback can:
 * - Return HookOutcome::proceed() to continue unchanged
 * - Return HookOutcome::proceed($modifiedContext) to modify the initial state
 * - Return HookOutcome::stop($reason) to prevent execution entirely
 *
 * @example
 * // Initialize monitoring
 * $hook = new ExecutionStartHook(
 *     callback: function (ExecutionHookContext $ctx): HookOutcome {
 *         $this->metrics->startTracking($ctx->state()->agentId);
 *         return HookOutcome::proceed(
 *             $ctx->withMetadata('execution_started', microtime(true))
 *         );
 *     },
 * );
 *
 * @example
 * // Validate prerequisites
 * $hook = new ExecutionStartHook(
 *     callback: function (ExecutionHookContext $ctx): HookOutcome {
 *         if (!$this->hasRequiredCredentials()) {
 *             return HookOutcome::stop('Missing required credentials');
 *         }
 *         return HookOutcome::proceed();
 *     },
 * );
 */
final readonly class ExecutionStartHook implements Hook
{
    /** @var Closure(ExecutionHookContext): (HookOutcome|AgentState|void) */
    private Closure $callback;

    /**
     * @param callable(ExecutionHookContext): (HookOutcome|AgentState|void) $callback
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
        // Only process ExecutionStart events
        if (!$context instanceof ExecutionHookContext || $context->eventType() !== HookEvent::ExecutionStart) {
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
            // If stopped, return immediately
            if ($result->isStopped()) {
                return $result;
            }
            // If proceed with modified context, pass it along
            $effectiveContext = $result->context() ?? $context;
            return $next($effectiveContext);
        }

        // Handle AgentState = proceed with modified state
        if ($result instanceof AgentState) {
            return $next($context->withState($result));
        }

        // Anything else = proceed unchanged
        return $next($context);
    }
}

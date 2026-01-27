<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Closure;
use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;
use Cognesy\Agents\AgentHooks\Contracts\HookMatcher;
use Cognesy\Agents\AgentHooks\Data\ExecutionHookContext;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;
use Cognesy\Agents\AgentHooks\Enums\HookType;

/**
 * Hook for intercepting agent execution start.
 *
 * Fired once when agent.run() begins, before any steps are executed.
 *
 * The callback must return a HookOutcome:
 * - HookOutcome::proceed() to continue unchanged
 * - HookOutcome::proceed($ctx->withState($newState)) to modify the initial state
 * - HookOutcome::stop($reason) to prevent execution entirely
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
    /** @var Closure(ExecutionHookContext): HookOutcome */
    private Closure $callback;

    /**
     * @param callable(ExecutionHookContext): HookOutcome $callback
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
        if (!$context instanceof ExecutionHookContext || $context->eventType() !== HookType::ExecutionStart) {
            return $next($context);
        }

        // Skip if matcher doesn't match
        if ($this->matcher !== null && !$this->matcher->matches($context)) {
            return $next($context);
        }

        // Execute callback - must return HookOutcome
        $outcome = ($this->callback)($context);

        // If stopped, return immediately
        if ($outcome->isStopped()) {
            return $outcome;
        }

        // Pass along (with potentially modified context)
        $effectiveContext = $outcome->context() ?? $context;
        return $next($effectiveContext);
    }
}

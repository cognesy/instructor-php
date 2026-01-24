<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Hooks\Hooks;

use Closure;
use Cognesy\Agents\Agent\Hooks\Contracts\Hook;
use Cognesy\Agents\Agent\Hooks\Contracts\HookContext;
use Cognesy\Agents\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Agents\Agent\Hooks\Data\ExecutionHookContext;
use Cognesy\Agents\Agent\Hooks\Data\HookOutcome;
use Cognesy\Agents\Agent\Hooks\Enums\HookType;

/**
 * Hook for intercepting agent execution end.
 *
 * Fired once when agent.run() completes (success or failure),
 * after all steps have been executed.
 *
 * The callback must return a HookOutcome:
 * - HookOutcome::proceed() to continue (stop/block are ignored since execution is complete)
 *
 * Note: ExecutionEnd hooks cannot stop or block - execution is already complete.
 *
 * @example
 * // Finalize monitoring
 * $hook = new ExecutionEndHook(
 *     callback: function (ExecutionHookContext $ctx): HookOutcome {
 *         $started = $ctx->get('execution_started');
 *         if ($started !== null) {
 *             $duration = microtime(true) - $started;
 *             $this->metrics->recordExecution($ctx->state()->agentId, $duration);
 *         }
 *         return HookOutcome::proceed();
 *     },
 * );
 *
 * @example
 * // Log completion
 * $hook = new ExecutionEndHook(
 *     callback: function (ExecutionHookContext $ctx): HookOutcome {
 *         $state = $ctx->state();
 *         $this->logger->info("Execution completed", [
 *             'agentId' => $state->agentId,
 *             'status' => $state->status()->value,
 *             'steps' => $state->stepCount(),
 *         ]);
 *         return HookOutcome::proceed();
 *     },
 * );
 */
final readonly class ExecutionEndHook implements Hook
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
        // Only process ExecutionEnd events
        if (!$context instanceof ExecutionHookContext || $context->eventType() !== HookType::ExecutionEnd) {
            return $next($context);
        }

        // Skip if matcher doesn't match
        if ($this->matcher !== null && !$this->matcher->matches($context)) {
            return $next($context);
        }

        // Execute callback - must return HookOutcome
        $outcome = ($this->callback)($context);

        // ExecutionEnd hooks can't stop - ignore stop/block outcomes
        $effectiveContext = $outcome->context() ?? $context;
        return $next($effectiveContext);
    }
}

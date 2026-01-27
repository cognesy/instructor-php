<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Closure;
use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;
use Cognesy\Agents\AgentHooks\Contracts\HookMatcher;
use Cognesy\Agents\AgentHooks\Data\FailureHookContext;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;
use Cognesy\Agents\AgentHooks\Enums\HookType;

/**
 * Hook for handling agent failures.
 *
 * Fired when the agent encounters an unrecoverable error.
 * Use this for error logging, alerting, and cleanup.
 *
 * The callback must return a HookOutcome:
 * - HookOutcome::proceed() to continue processing (stop/block are ignored)
 *
 * Note: AgentFailed hooks cannot prevent or recover from the failure -
 * execution has already failed. They are for observation and cleanup.
 *
 * @example
 * // Log failures
 * $hook = new AgentFailedHook(
 *     callback: function (FailureHookContext $ctx): HookOutcome {
 *         $exception = $ctx->exception();
 *         $state = $ctx->state();
 *
 *         $this->logger->error("Agent failed: {$exception->getMessage()}", [
 *             'agentId' => $state->agentId,
 *             'step' => $state->stepCount(),
 *             'trace' => $exception->getTraceAsString(),
 *         ]);
 *         return HookOutcome::proceed();
 *     },
 * );
 *
 * @example
 * // Send alerts
 * $hook = new AgentFailedHook(
 *     callback: function (FailureHookContext $ctx): HookOutcome {
 *         $this->alerting->sendAlert(
 *             title: "Agent {$ctx->state()->agentId} failed",
 *             message: $ctx->errorMessage(),
 *             severity: 'critical',
 *         );
 *         return HookOutcome::proceed();
 *     },
 * );
 */
final readonly class AgentFailedHook implements Hook
{
    /** @var Closure(FailureHookContext): HookOutcome */
    private Closure $callback;

    /**
     * @param callable(FailureHookContext): HookOutcome $callback
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
        // Only process AgentFailed events
        if (!$context instanceof FailureHookContext || $context->eventType() !== HookType::AgentFailed) {
            return $next($context);
        }

        // Skip if matcher doesn't match
        if ($this->matcher !== null && !$this->matcher->matches($context)) {
            return $next($context);
        }

        // Execute callback - must return HookOutcome
        $outcome = ($this->callback)($context);

        // AgentFailed hooks can't prevent failure - ignore stop/block outcomes
        $effectiveContext = $outcome->context() ?? $context;
        return $next($effectiveContext);
    }
}

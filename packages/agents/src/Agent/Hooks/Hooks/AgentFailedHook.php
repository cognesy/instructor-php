<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Hooks\Hooks;

use Closure;
use Cognesy\Agents\Agent\Hooks\Contracts\Hook;
use Cognesy\Agents\Agent\Hooks\Contracts\HookContext;
use Cognesy\Agents\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Agents\Agent\Hooks\Data\FailureHookContext;
use Cognesy\Agents\Agent\Hooks\Data\HookEvent;
use Cognesy\Agents\Agent\Hooks\Data\HookOutcome;

/**
 * Hook for handling agent failures.
 *
 * Fired when the agent encounters an unrecoverable error.
 * Use this for error logging, alerting, and cleanup.
 *
 * The callback can:
 * - Return HookOutcome::proceed() to continue processing
 * - Return void/anything else to proceed unchanged
 *
 * Note: AgentFailed hooks cannot prevent or recover from the failure -
 * execution has already failed. They are for observation and cleanup.
 *
 * @example
 * // Log failures
 * $hook = new AgentFailedHook(
 *     callback: function (FailureHookContext $ctx): void {
 *         $exception = $ctx->exception();
 *         $state = $ctx->state();
 *
 *         $this->logger->error("Agent failed: {$exception->getMessage()}", [
 *             'agentId' => $state->agentId,
 *             'step' => $state->stepCount(),
 *             'trace' => $exception->getTraceAsString(),
 *         ]);
 *     },
 * );
 *
 * @example
 * // Send alerts
 * $hook = new AgentFailedHook(
 *     callback: function (FailureHookContext $ctx): void {
 *         $this->alerting->sendAlert(
 *             title: "Agent {$ctx->state()->agentId} failed",
 *             message: $ctx->errorMessage(),
 *             severity: 'critical',
 *         );
 *     },
 * );
 */
final readonly class AgentFailedHook implements Hook
{
    /** @var Closure(FailureHookContext): (HookOutcome|void) */
    private Closure $callback;

    /**
     * @param callable(FailureHookContext): (HookOutcome|void) $callback
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
        if (!$context instanceof FailureHookContext || $context->eventType() !== HookEvent::AgentFailed) {
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
            // AgentFailed hooks can't prevent failure - ignore stop/block outcomes
            $effectiveContext = $result->context() ?? $context;
            return $next($effectiveContext);
        }

        // Anything else = proceed unchanged
        return $next($context);
    }
}

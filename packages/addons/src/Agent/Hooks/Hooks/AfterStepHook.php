<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Hooks\Hooks;

use Closure;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Hooks\Contracts\Hook;
use Cognesy\Addons\Agent\Hooks\Contracts\HookContext;
use Cognesy\Addons\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Addons\Agent\Hooks\Data\HookEvent;
use Cognesy\Addons\Agent\Hooks\Data\HookOutcome;
use Cognesy\Addons\Agent\Hooks\Data\StepHookContext;

/**
 * Hook for intercepting step execution after it completes.
 *
 * The callback can:
 * - Return HookOutcome::proceed() to continue unchanged
 * - Return HookOutcome::proceed($modifiedContext) to modify the state
 * - Return HookOutcome::stop($reason) to halt agent execution
 *
 * Simplified callback signatures are also supported:
 * - Return AgentState to proceed with modified state
 * - Return void/anything else to proceed unchanged
 *
 * @example
 * // Record step duration
 * $hook = new AfterStepHook(
 *     callback: function (StepHookContext $ctx): void {
 *         $started = $ctx->get('step_started');
 *         if ($started !== null) {
 *             $duration = microtime(true) - $started;
 *             $this->metrics->recordStepDuration($ctx->stepIndex(), $duration);
 *         }
 *     },
 * );
 *
 * @example
 * // Check for errors
 * $hook = new AfterStepHook(
 *     callback: function (StepHookContext $ctx): HookOutcome {
 *         if ($ctx->step()?->hasErrors()) {
 *             $this->logger->warning("Step {$ctx->stepNumber()} had errors");
 *         }
 *         return HookOutcome::proceed();
 *     },
 * );
 */
final readonly class AfterStepHook implements Hook
{
    private Closure $callback;

    /**
     * @param callable(StepHookContext): (HookOutcome|AgentState|void) $callback
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
        // Only process AfterStep events
        if (!$context instanceof StepHookContext || $context->eventType() !== HookEvent::AfterStep) {
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

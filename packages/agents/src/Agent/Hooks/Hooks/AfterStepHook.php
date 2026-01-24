<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Hooks\Hooks;

use Closure;
use Cognesy\Agents\Agent\Hooks\Contracts\Hook;
use Cognesy\Agents\Agent\Hooks\Contracts\HookContext;
use Cognesy\Agents\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Agents\Agent\Hooks\Data\HookOutcome;
use Cognesy\Agents\Agent\Hooks\Data\StepHookContext;
use Cognesy\Agents\Agent\Hooks\Enums\HookType;

/**
 * Hook for intercepting step execution after it completes.
 *
 * The callback must return a HookOutcome:
 * - HookOutcome::proceed() to continue unchanged
 * - HookOutcome::proceed($ctx->withState($newState)) to modify the state
 * - HookOutcome::stop($reason) to halt agent execution
 *
 * @example
 * // Record step duration
 * $hook = new AfterStepHook(
 *     callback: function (StepHookContext $ctx): HookOutcome {
 *         $started = $ctx->get('step_started');
 *         if ($started !== null) {
 *             $duration = microtime(true) - $started;
 *             $this->metrics->recordStepDuration($ctx->stepIndex(), $duration);
 *         }
 *         return HookOutcome::proceed();
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
    /** @var Closure(StepHookContext): HookOutcome */
    private Closure $callback;

    /**
     * @param callable(StepHookContext): HookOutcome $callback
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
        if (!$context instanceof StepHookContext || $context->eventType() !== HookType::AfterStep) {
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

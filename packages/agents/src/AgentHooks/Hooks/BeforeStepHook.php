<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Closure;
use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;
use Cognesy\Agents\AgentHooks\Contracts\HookMatcher;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;
use Cognesy\Agents\AgentHooks\Data\StepHookContext;
use Cognesy\Agents\AgentHooks\Enums\HookType;

/**
 * Hook for intercepting step execution before it begins.
 *
 * The callback must return a HookOutcome:
 * - HookOutcome::proceed() to continue unchanged
 * - HookOutcome::proceed($ctx->withState($newState)) to modify the state
 * - HookOutcome::stop($reason) to halt agent execution
 *
 * @example
 * // Add timing metadata
 * $hook = new BeforeStepHook(
 *     callback: fn(StepHookContext $ctx): HookOutcome => HookOutcome::proceed(
 *         $ctx->withMetadata('step_started', microtime(true))
 *     ),
 * );
 *
 * @example
 * // Log and proceed
 * $hook = new BeforeStepHook(
 *     callback: function (StepHookContext $ctx): HookOutcome {
 *         $this->logger->info("Starting step {$ctx->stepNumber()}");
 *         return HookOutcome::proceed();
 *     },
 * );
 */
final readonly class BeforeStepHook implements Hook
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
        // Only process BeforeStep events
        if (!$context instanceof StepHookContext || $context->eventType() !== HookType::BeforeStep) {
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

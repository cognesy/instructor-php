<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Hooks\Adapters;

use Cognesy\Addons\Agent\Hooks\Contracts\Hook;
use Cognesy\Addons\Agent\Hooks\Contracts\HookContext;
use Cognesy\Addons\Agent\Hooks\Data\HookEvent;
use Cognesy\Addons\Agent\Hooks\Data\HookOutcome;
use Cognesy\Addons\Agent\Hooks\Data\StopHookContext;
use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;

/**
 * Adapter that wraps existing ContinuationCriteria to work with the new Hook system.
 *
 * This adapter allows existing CanEvaluateContinuation implementations to be used
 * with the unified HookStack as stop hooks.
 *
 * The adapter:
 * - Only processes StopHookContext (stop events)
 * - Evaluates the criterion against the state
 * - Converts ForbidContinuation to HookOutcome::stop()
 * - Converts AllowContinuation to HookOutcome::block() (force continuation)
 * - Converts AllowStop to HookOutcome::proceed() (allow stop)
 *
 * @example
 * // Wrap existing criterion
 * $criterion = new StepsLimit(10, fn($state) => $state->stepCount());
 * $hook = new ContinuationCriteriaAdapter($criterion);
 *
 * // Use with HookStack
 * $stack = new HookStack();
 * $stack = $stack->with($hook);
 */
final readonly class ContinuationCriteriaAdapter implements Hook
{
    /**
     * @param CanEvaluateContinuation $criterion The criterion to adapt
     */
    public function __construct(
        private CanEvaluateContinuation $criterion,
    ) {}

    #[\Override]
    public function handle(HookContext $context, callable $next): HookOutcome
    {
        // Only process stop events
        if (!$context instanceof StopHookContext) {
            return $next($context);
        }

        // Only process Stop events (not SubagentStop)
        if ($context->eventType() !== HookEvent::Stop) {
            return $next($context);
        }

        // Evaluate the criterion
        $evaluation = $this->criterion->evaluate($context->state());

        // Convert evaluation to HookOutcome
        return match ($evaluation->decision) {
            // ForbidContinuation = hard stop (e.g., limit exceeded)
            ContinuationDecision::ForbidContinuation => HookOutcome::stop(
                $evaluation->reason ?? 'Continuation forbidden by criterion',
            ),

            // AllowContinuation = force continuation (criterion wants more work)
            ContinuationDecision::AllowContinuation => HookOutcome::block(
                $evaluation->reason ?? 'Continuation requested by criterion',
            ),

            // AllowStop = allow stop, continue chain
            ContinuationDecision::AllowStop => $next($context),

            // RequestContinuation = similar to AllowContinuation
            ContinuationDecision::RequestContinuation => HookOutcome::block(
                $evaluation->reason ?? 'Continuation requested by criterion',
            ),
        };
    }
}

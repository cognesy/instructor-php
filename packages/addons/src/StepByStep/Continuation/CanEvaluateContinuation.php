<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

/**
 * Unified continuation criterion contract.
 *
 * Replaces the previous decision/stop-reason/explanation interfaces.
 *
 * Each criterion returns a complete ContinuationEvaluation containing:
 *   - decision (ForbidContinuation, AllowContinuation, RequestContinuation, AllowStop)
 *   - reason (human-readable explanation)
 *   - stopReason (optional, for when stopping)
 *   - context (optional, additional data)
 *
 * @template TState of object
 */
interface CanEvaluateContinuation
{
    /**
     * Evaluate the state and return a complete evaluation.
     *
     * @param TState $state
     * @return ContinuationEvaluation The criterion's full evaluation
     */
    public function evaluate(object $state): ContinuationEvaluation;
}

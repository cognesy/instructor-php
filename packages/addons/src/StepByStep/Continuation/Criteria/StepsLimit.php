<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

/**
 * Guard: Forbids continuation once the number of completed steps reaches a configured maximum.
 *
 * Returns ForbidContinuation when limit exceeded (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 *
 * @template TState of object
 * @implements CanEvaluateContinuation<TState>
 */
final readonly class StepsLimit implements CanEvaluateContinuation
{
    /** @var Closure(TState): int */
    private Closure $stepCounter;

    /**
     * @param Closure(TState): int $stepCounter Extracts the number of completed steps for the state.
     */
    public function __construct(
        private int $maxSteps,
        callable $stepCounter,
    ) {
        $this->stepCounter = Closure::fromCallable($stepCounter);
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function evaluate(object $state): ContinuationEvaluation {
        /** @var TState $state */
        $currentSteps = ($this->stepCounter)($state);
        $exceeded = $currentSteps >= $this->maxSteps;

        $decision = $exceeded
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowContinuation;

        $reason = $exceeded
            ? sprintf('Step limit reached: %d/%d', $currentSteps, $this->maxSteps)
            : sprintf('Steps under limit: %d/%d', $currentSteps, $this->maxSteps);

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: [
                'currentSteps' => $currentSteps,
                'maxSteps' => $this->maxSteps,
            ],
            stopReason: $exceeded ? StopReason::StepsLimitReached : null,
        );
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

/**
 * Guard: Forbids continuation after a configurable number of consecutive failed steps.
 *
 * Returns ForbidContinuation when retry limit exceeded (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 *
 * @template TState of object
 * @template TStep of object
 * @implements CanEvaluateContinuation<TState>
 */
final readonly class RetryLimit implements CanEvaluateContinuation
{
    /** @var Closure(TState): iterable<TStep> */
    private Closure $stepSequence;
    /** @var Closure(TStep): bool */
    private Closure $stepHasError;

    /**
     * @param Closure(TState): iterable<TStep> $stepSequence Provides steps in chronological order.
     * @param Closure(TStep): bool $stepHasError Indicates whether the step should be treated as a failure.
     */
    public function __construct(
        private int $maxConsecutiveFailures,
        callable $stepSequence,
        callable $stepHasError,
    ) {
        $this->stepSequence = Closure::fromCallable($stepSequence);
        $this->stepHasError = Closure::fromCallable($stepHasError);
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function evaluate(object $state): ContinuationEvaluation {
        /** @var TState $state */
        $steps = $this->collectSteps($state);
        if ($steps === []) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowContinuation,
                reason: 'No steps yet, allowing continuation',
                context: [
                    'consecutiveFailures' => 0,
                    'maxConsecutiveFailures' => $this->maxConsecutiveFailures,
                ],
            );
        }

        $failedTail = 0;
        for ($i = count($steps) - 1; $i >= 0; $i--) {
            $step = $steps[$i];
            if (($this->stepHasError)($step) === false) {
                break;
            }
            $failedTail++;
            if ($failedTail > $this->maxConsecutiveFailures) {
                break;
            }
        }

        $exceeded = $failedTail >= $this->maxConsecutiveFailures;
        $decision = $exceeded
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowContinuation;

        $reason = $exceeded
            ? sprintf('Retry limit reached: %d/%d consecutive failures', $failedTail, $this->maxConsecutiveFailures)
            : sprintf('Consecutive failures under limit: %d/%d', $failedTail, $this->maxConsecutiveFailures);

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: [
                'consecutiveFailures' => $failedTail,
                'maxConsecutiveFailures' => $this->maxConsecutiveFailures,
            ],
            stopReason: $exceeded ? StopReason::RetryLimitReached : null,
        );
    }

    /**
     * @param TState $state
     * @return array<int, TStep>
     */
    private function collectSteps(object $state): array {
        $steps = [];
        foreach (($this->stepSequence)($state) as $step) {
            $steps[] = $step;
        }
        return $steps;
    }
}

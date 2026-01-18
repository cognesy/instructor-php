<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\CanProvideStopReason;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

/**
 * Guard: Forbids continuation after a configurable number of consecutive failed steps.
 *
 * Returns ForbidContinuation when retry limit exceeded (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 *
 * @template TState of object
 * @template TStep of object
 * @implements CanDecideToContinue<TState>
 */
final readonly class RetryLimit implements CanDecideToContinue, CanProvideStopReason
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
    public function decide(object $state): ContinuationDecision {
        /** @var TState $state */
        $steps = $this->collectSteps($state);
        if ($steps === []) {
            return ContinuationDecision::AllowContinuation;
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

        // Under limit: allow continuation (guard permits)
        // At/over limit: forbid continuation (guard denies)
        return $failedTail < $this->maxConsecutiveFailures
            ? ContinuationDecision::AllowContinuation
            : ContinuationDecision::ForbidContinuation;
    }

    #[\Override]
    public function stopReason(object $state, ContinuationDecision $decision): ?StopReason {
        return match ($decision) {
            ContinuationDecision::ForbidContinuation => StopReason::GuardForbade,
            default => null,
        };
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

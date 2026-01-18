<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\CanProvideStopReason;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

/**
 * Guard: Forbids continuation once the number of completed steps reaches a configured maximum.
 *
 * Returns ForbidContinuation when limit exceeded (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
final readonly class StepsLimit implements CanDecideToContinue, CanProvideStopReason
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
    public function decide(object $state): ContinuationDecision {
        /** @var TState $state */
        $currentSteps = ($this->stepCounter)($state);

        // Under limit: allow continuation (guard permits)
        // At/over limit: forbid continuation (guard denies)
        return $currentSteps < $this->maxSteps
            ? ContinuationDecision::AllowContinuation
            : ContinuationDecision::ForbidContinuation;
    }

    #[\Override]
    public function stopReason(object $state, ContinuationDecision $decision): ?StopReason {
        return match ($decision) {
            ContinuationDecision::ForbidContinuation => StopReason::StepsLimitReached,
            default => null,
        };
    }
}

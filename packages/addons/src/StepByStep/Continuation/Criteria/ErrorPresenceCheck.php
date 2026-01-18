<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\CanProvideStopReason;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

/**
 * Guard: Forbids continuation when the current step reports execution errors.
 *
 * Returns ForbidContinuation when errors present (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
final readonly class ErrorPresenceCheck implements CanDecideToContinue, CanProvideStopReason
{
    /** @var Closure(TState): bool */
    private Closure $hasErrorsResolver;

    /**
     * @param Closure(TState): bool $hasErrorsResolver
     */
    public function __construct(callable $hasErrorsResolver) {
        $this->hasErrorsResolver = Closure::fromCallable($hasErrorsResolver);
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function decide(object $state): ContinuationDecision {
        /** @var TState $state */
        $hasErrors = ($this->hasErrorsResolver)($state);

        // No errors: allow continuation (guard permits)
        // Has errors: forbid continuation (guard denies)
        return $hasErrors
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowContinuation;
    }

    #[\Override]
    public function stopReason(object $state, ContinuationDecision $decision): ?StopReason {
        return match ($decision) {
            ContinuationDecision::ForbidContinuation => StopReason::GuardForbade,
            default => null,
        };
    }
}

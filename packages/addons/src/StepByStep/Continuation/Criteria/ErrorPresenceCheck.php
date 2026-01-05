<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;

/**
 * Guard: Forbids continuation when the current step reports execution errors.
 *
 * Returns ForbidContinuation when errors present (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
final readonly class ErrorPresenceCheck implements CanDecideToContinue
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
}

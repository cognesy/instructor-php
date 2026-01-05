<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

use Closure;

/**
 * Wraps a callable predicate as a continuation criterion.
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
final readonly class CallableCriterion implements CanDecideToContinue
{
    /** @var Closure(TState): ContinuationDecision */
    private Closure $predicate;

    /**
     * @param callable(TState): ContinuationDecision $predicate
     */
    public function __construct(callable $predicate) {
        $this->predicate = Closure::fromCallable($predicate);
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function decide(object $state): ContinuationDecision {
        /** @var TState $state */
        return ($this->predicate)($state);
    }
}

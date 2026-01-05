<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

/**
 * Flat collection of continuation criteria with priority-based resolution.
 *
 * Two categories of criteria:
 *   - Guards (limits, error checks): ForbidContinuation / AllowContinuation
 *   - Work drivers (tool calls, self-critic): RequestContinuation / AllowStop
 *
 * Resolution is order-independent:
 *   1. If ANY criterion returns ForbidContinuation → STOP (guard denied)
 *   2. Else if ANY criterion returns RequestContinuation → CONTINUE (work requested)
 *   3. Else → STOP (no work requested)
 *
 * Usage:
 *   $criteria = new ContinuationCriteria(
 *       new StepsLimit(10),           // Guard
 *       new TokenUsageLimit(16384),   // Guard
 *       new ToolCallPresenceCheck(...), // Work driver
 *       new SelfCriticContinuationCheck(...), // Work driver
 *   );
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
class ContinuationCriteria implements CanDecideToContinue
{
    /** @var list<CanDecideToContinue<TState>> */
    private array $criteria;

    /**
     * @param CanDecideToContinue<TState> ...$criteria
     */
    public function __construct(CanDecideToContinue ...$criteria) {
        $this->criteria = $criteria;
    }

    /**
     * Create criteria collection.
     *
     * @template T of object
     * @param CanDecideToContinue<T> ...$criteria
     * @return self<T>
     */
    public static function from(CanDecideToContinue ...$criteria): self {
        return new self(...$criteria);
    }

    /**
     * Create a criterion from a predicate callback.
     *
     * @template T of object
     * @param callable(T): ContinuationDecision $predicate
     * @return CanDecideToContinue<T>
     */
    public static function when(callable $predicate): CanDecideToContinue {
        /** @var CanDecideToContinue<T> */
        return new CallableCriterion($predicate); // @phpstan-ignore argument.type
    }

    public function isEmpty(): bool {
        return $this->criteria === [];
    }

    /**
     * @param CanDecideToContinue<TState> ...$criteria
     * @return self<TState>
     */
    public function withCriteria(CanDecideToContinue ...$criteria): self {
        return new self(...[...$this->criteria, ...$criteria]);
    }

    /**
     * Boolean convenience method for backward compatibility.
     * Returns true if continuation should proceed, false otherwise.
     *
     * @param TState $state
     */
    public function canContinue(object $state): bool {
        if ($this->criteria === []) {
            return false;
        }

        $decisions = array_map(
            fn(CanDecideToContinue $criterion) => $criterion->decide($state),
            $this->criteria
        );

        return ContinuationDecision::canContinueWith(...$decisions);
    }

    /**
     * Collect all decisions and resolve using priority logic.
     *
     * @param TState $state
     */
    #[\Override]
    public function decide(object $state): ContinuationDecision {
        if ($this->criteria === []) {
            return ContinuationDecision::AllowStop;
        }

        $decisions = array_map(
            fn(CanDecideToContinue $criterion) => $criterion->decide($state),
            $this->criteria
        );

        // Use resolution logic: Forbid > Request > (Allow + Stop)
        $shouldContinue = ContinuationDecision::canContinueWith(...$decisions);

        return $shouldContinue
            ? ContinuationDecision::RequestContinuation
            : ContinuationDecision::AllowStop;
    }
}

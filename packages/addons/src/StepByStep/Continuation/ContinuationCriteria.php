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
 * @implements CanEvaluateContinuation<TState>
 */
class ContinuationCriteria implements CanEvaluateContinuation
{
    /** @var list<CanEvaluateContinuation<TState>> */
    private array $criteria;

    /**
     * @param CanEvaluateContinuation<TState> ...$criteria
     */
    public function __construct(CanEvaluateContinuation ...$criteria) {
        $this->criteria = $criteria;
    }

    /**
     * Create criteria collection.
     *
     * @template T of object
     * @param CanEvaluateContinuation<T> ...$criteria
     * @return self<T>
     */
    public static function from(CanEvaluateContinuation ...$criteria): self {
        return new self(...$criteria);
    }

    /**
     * Create a criterion from a predicate callback.
     *
     * @template T of object
     * @param callable(T): ContinuationDecision $predicate
     * @return CanEvaluateContinuation<T>
     */
    public static function when(callable $predicate): CanEvaluateContinuation {
        /** @var CanEvaluateContinuation<T> */
        return new CallableCriterion($predicate); // @phpstan-ignore argument.type
    }

    public function isEmpty(): bool {
        return $this->criteria === [];
    }

    /**
     * @param CanEvaluateContinuation<TState> ...$criteria
     * @return self<TState>
     */
    public function withCriteria(CanEvaluateContinuation ...$criteria): self {
        return new self(...[...$this->criteria, ...$criteria]);
    }

    /**
     * Boolean convenience method for backward compatibility.
     * Returns true if continuation should proceed, false otherwise.
     *
     * @param TState $state
     */
    public function canContinue(object $state): bool {
        return $this->evaluateAll($state)->shouldContinue();
    }

    /**
     * Evaluate this composite criterion and return a single evaluation.
     * Implements CanEvaluateContinuation for composability.
     *
     * @param TState $state
     */
    #[\Override]
    public function evaluate(object $state): ContinuationEvaluation {
        $outcome = $this->evaluateAll($state);
        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $outcome->decision(),
            reason: sprintf('Aggregate of %d criteria', count($this->criteria)),
            context: ['criteriaCount' => count($this->criteria)],
            stopReason: $outcome->stopReason(),
        );
    }

    /**
     * Evaluate all criteria and return the full outcome.
     *
     * @param TState $state
     */
    public function evaluateAll(object $state): ContinuationOutcome {
        if ($this->criteria === []) {
            return ContinuationOutcome::empty();
        }

        $evaluations = [];
        foreach ($this->criteria as $criterion) {
            $evaluations[] = $criterion->evaluate($state);
        }

        return ContinuationOutcome::fromEvaluations($evaluations);
    }
}

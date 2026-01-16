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
        return $this->evaluate($state)->shouldContinue();
    }

    /**
     * Collect all decisions and resolve using priority logic.
     *
     * @param TState $state
     */
    #[\Override]
    public function decide(object $state): ContinuationDecision {
        return $this->evaluate($state)->decision;
    }

    /**
     * Evaluate all criteria and return the full outcome.
     *
     * @param TState $state
     */
    public function evaluate(object $state): ContinuationOutcome {
        if ($this->criteria === []) {
            return new ContinuationOutcome(
                decision: ContinuationDecision::AllowStop,
                shouldContinue: false,
                resolvedBy: self::class,
                stopReason: StopReason::Completed,
                evaluations: [],
            );
        }

        $evaluations = [];
        $resolvedBy = null;
        $stopReason = StopReason::Completed;

        foreach ($this->criteria as $criterion) {
            if ($criterion instanceof CanExplainContinuation) {
                $evaluation = $criterion->explain($state);
            } else {
                $evaluation = ContinuationEvaluation::fromDecision($criterion::class, $criterion->decide($state));
            }
            $evaluations[] = $evaluation;

            if ($evaluation->decision === ContinuationDecision::ForbidContinuation && $resolvedBy === null) {
                $resolvedBy = $evaluation->criterionClass;
                $stopReason = $this->inferStopReason($evaluation);
            }
        }

        $decisions = array_map(
            static fn(ContinuationEvaluation $evaluation): ContinuationDecision => $evaluation->decision,
            $evaluations,
        );

        $shouldContinue = ContinuationDecision::canContinueWith(...$decisions);
        if ($shouldContinue && $resolvedBy === null) {
            foreach ($evaluations as $evaluation) {
                if ($evaluation->decision === ContinuationDecision::RequestContinuation) {
                    $resolvedBy = $evaluation->criterionClass;
                    break;
                }
            }
        }

        $decision = ContinuationDecision::AllowStop;
        if ($shouldContinue) {
            $decision = ContinuationDecision::RequestContinuation;
            $stopReason = StopReason::Completed;
        }

        return new ContinuationOutcome(
            decision: $decision,
            shouldContinue: $shouldContinue,
            resolvedBy: $resolvedBy ?? 'aggregate',
            stopReason: $stopReason,
            evaluations: $evaluations,
        );
    }

    private function inferStopReason(ContinuationEvaluation $evaluation): StopReason {
        return match (true) {
            str_contains($evaluation->criterionClass, 'StepsLimit') => StopReason::StepsLimitReached,
            str_contains($evaluation->criterionClass, 'TokenUsageLimit') => StopReason::TokenLimitReached,
            str_contains($evaluation->criterionClass, 'ExecutionTimeLimit') => StopReason::TimeLimitReached,
            str_contains($evaluation->criterionClass, 'ErrorPolicy') => StopReason::ErrorForbade,
            str_contains($evaluation->criterionClass, 'FinishReason') => StopReason::FinishReasonReceived,
            default => StopReason::GuardForbade,
        };
    }
}

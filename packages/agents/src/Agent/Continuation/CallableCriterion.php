<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Continuation;

use Closure;
use Cognesy\Agents\Agent\Data\AgentState;

/**
 * Wraps a callable predicate as a continuation criterion.
 */
final readonly class CallableCriterion implements CanEvaluateContinuation
{
    /** @var Closure(AgentState): ContinuationDecision */
    private Closure $predicate;

    /**
     * @param callable(AgentState): ContinuationDecision $predicate
     */
    public function __construct(callable $predicate) {
        $this->predicate = Closure::fromCallable($predicate);
    }

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
        $decision = ($this->predicate)($state);

        return ContinuationEvaluation::fromDecision(
            criterionClass: self::class,
            decision: $decision,
        );
    }
}

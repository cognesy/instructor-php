<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Continuation\Criteria;

use Closure;
use Cognesy\Agents\Core\Continuation\Contracts\CanEvaluateContinuation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Data\AgentState;

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

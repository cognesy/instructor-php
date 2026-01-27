<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Continuation;

use Cognesy\Agents\Core\Continuation\Contracts\CanEvaluateContinuation;
use Cognesy\Agents\Core\Continuation\Contracts\CanStartExecution;
use Cognesy\Agents\Core\Continuation\Criteria\CallableCriterion;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Data\AgentState;
use DateTimeImmutable;

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
 */
class ContinuationCriteria implements CanEvaluateContinuation
{
    /** @var list<CanEvaluateContinuation> */
    private array $criteria;

    public function __construct(CanEvaluateContinuation ...$criteria) {
        $this->criteria = $criteria;
    }

    public static function from(CanEvaluateContinuation ...$criteria): self {
        return new self(...$criteria);
    }

    /**
     * Create a criterion from a predicate callback.
     *
     * @param callable(AgentState): ContinuationDecision $predicate
     */
    public static function when(callable $predicate): CanEvaluateContinuation {
        return new CallableCriterion($predicate);
    }

    public function isEmpty(): bool {
        return $this->criteria === [];
    }

    public function withCriteria(CanEvaluateContinuation ...$criteria): self {
        return new self(...[...$this->criteria, ...$criteria]);
    }

    public function executionStarted(DateTimeImmutable $startedAt): void {
        foreach ($this->criteria as $criterion) {
            if (!$criterion instanceof CanStartExecution) {
                continue;
            }
            $criterion->executionStarted($startedAt);
        }
    }

    /**
     * Boolean convenience method for backward compatibility.
     * Returns true if continuation should proceed, false otherwise.
     */
    public function canContinue(AgentState $state): bool {
        return $this->evaluateAll($state)->shouldContinue();
    }

    /**
     * Evaluate this composite criterion and return a single evaluation.
     * Implements CanEvaluateContinuation for composability.
     */
    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
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
     */
    public function evaluateAll(AgentState $state): ContinuationOutcome {
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

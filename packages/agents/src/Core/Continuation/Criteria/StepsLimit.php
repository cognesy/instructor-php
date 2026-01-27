<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Continuation\Criteria;

use Closure;
use Cognesy\Agents\Core\Continuation\Contracts\CanEvaluateContinuation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Guard: Forbids continuation once the number of completed steps reaches a configured maximum.
 *
 * Returns ForbidContinuation when limit exceeded (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 */
final readonly class StepsLimit implements CanEvaluateContinuation
{
    /** @var Closure(AgentState): int */
    private Closure $stepCounter;

    /**
     * @param Closure(AgentState): int $stepCounter Extracts the number of completed steps for the state.
     */
    public function __construct(
        private int $maxSteps,
        callable $stepCounter,
    ) {
        $this->stepCounter = Closure::fromCallable($stepCounter);
    }

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
        $currentSteps = ($this->stepCounter)($state);
        $exceeded = $currentSteps >= $this->maxSteps;

        $decision = $exceeded
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowContinuation;

        $reason = $exceeded
            ? sprintf('Step limit reached: %d/%d', $currentSteps, $this->maxSteps)
            : sprintf('Steps under limit: %d/%d', $currentSteps, $this->maxSteps);

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: [
                'currentSteps' => $currentSteps,
                'maxSteps' => $this->maxSteps,
            ],
            stopReason: $exceeded ? StopReason::StepsLimitReached : null,
        );
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Continuation\Criteria;

use Closure;
use Cognesy\Agents\Agent\Continuation\CanEvaluateContinuation;
use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Continuation\ContinuationEvaluation;
use Cognesy\Agents\Agent\Continuation\StopReason;
use Cognesy\Agents\Agent\Data\AgentState;

/**
 * Guard: Forbids continuation after a configurable number of consecutive failed steps.
 *
 * Returns ForbidContinuation when retry limit exceeded (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 */
final readonly class RetryLimit implements CanEvaluateContinuation
{
    /** @var Closure(AgentState): iterable<object> */
    private Closure $stepSequence;
    /** @var Closure(object): bool */
    private Closure $stepHasError;

    /**
     * @param Closure(AgentState): iterable<object> $stepSequence Provides steps in chronological order.
     * @param Closure(object): bool $stepHasError Indicates whether the step should be treated as a failure.
     */
    public function __construct(
        private int $maxConsecutiveFailures,
        callable $stepSequence,
        callable $stepHasError,
    ) {
        $this->stepSequence = Closure::fromCallable($stepSequence);
        $this->stepHasError = Closure::fromCallable($stepHasError);
    }

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
        $steps = $this->collectSteps($state);
        if ($steps === []) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowContinuation,
                reason: 'No steps yet, allowing continuation',
                context: [
                    'consecutiveFailures' => 0,
                    'maxConsecutiveFailures' => $this->maxConsecutiveFailures,
                ],
            );
        }

        $failedTail = 0;
        for ($i = count($steps) - 1; $i >= 0; $i--) {
            $step = $steps[$i];
            if (($this->stepHasError)($step) === false) {
                break;
            }
            $failedTail++;
            if ($failedTail > $this->maxConsecutiveFailures) {
                break;
            }
        }

        $exceeded = $failedTail >= $this->maxConsecutiveFailures;
        $decision = $exceeded
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowContinuation;

        $reason = $exceeded
            ? sprintf('Retry limit reached: %d/%d consecutive failures', $failedTail, $this->maxConsecutiveFailures)
            : sprintf('Consecutive failures under limit: %d/%d', $failedTail, $this->maxConsecutiveFailures);

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: [
                'consecutiveFailures' => $failedTail,
                'maxConsecutiveFailures' => $this->maxConsecutiveFailures,
            ],
            stopReason: $exceeded ? StopReason::RetryLimitReached : null,
        );
    }

    /**
     * @return array<int, object>
     */
    private function collectSteps(AgentState $state): array {
        $steps = [];
        foreach (($this->stepSequence)($state) as $step) {
            $steps[] = $step;
        }
        return $steps;
    }
}

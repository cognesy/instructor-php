<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Continuation\Criteria;

use Cognesy\Agents\Agent\Continuation\CanStartExecution;
use Cognesy\Agents\Agent\Continuation\CanEvaluateContinuation;
use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Continuation\ContinuationEvaluation;
use Cognesy\Agents\Agent\Continuation\StopReason;
use Cognesy\Utils\Time\ClockInterface;
use Cognesy\Utils\Time\SystemClock;
use DateTimeImmutable;
use Cognesy\Agents\Agent\Data\AgentState;

/**
 * Guard: Forbids continuation once elapsed time between the execution start and current clock exceeds the limit.
 *
 * Timing is per Agent::execute() call. The start time is signaled by the orchestrator.
 * If no signal is provided, the first evaluation will initialize the start time lazily.
 *
 * Returns ForbidContinuation when time limit exceeded (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 */
final class ExecutionTimeLimit implements CanEvaluateContinuation, CanStartExecution
{
    private ClockInterface $clock;
    private int $maxSeconds;
    private ?DateTimeImmutable $executionStartedAt = null;

    /**
     * @param int $maxSeconds Maximum seconds allowed for a single execution.
     * @param ClockInterface|null $clock Optional clock for testing.
     */
    public function __construct(
        int $maxSeconds,
        ?ClockInterface $clock = null,
    ) {
        if ($maxSeconds <= 0) {
            throw new \InvalidArgumentException('Max seconds must be greater than zero.');
        }
        $this->maxSeconds = $maxSeconds;
        $this->clock = $clock ?? new SystemClock();
    }

    #[\Override]
    public function executionStarted(DateTimeImmutable $startedAt): void {
        $this->executionStartedAt = $startedAt;
    }

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
        $now = $this->clock->now();
        $startedAt = $this->executionStartedAt ?? $now;
        $this->executionStartedAt ??= $startedAt;
        $elapsedSeconds = $now->getTimestamp() - $startedAt->getTimestamp();
        $exceeded = $elapsedSeconds >= $this->maxSeconds;

        $decision = match ($exceeded) {
            true => ContinuationDecision::ForbidContinuation,
            default => ContinuationDecision::AllowContinuation,
        };

        $reason = match ($exceeded) {
            true => sprintf('Execution time limit reached: %ds/%ds', $elapsedSeconds, $this->maxSeconds),
            default => sprintf('Execution time under limit: %ds/%ds', $elapsedSeconds, $this->maxSeconds),
        };

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: [
                'elapsedSeconds' => $elapsedSeconds,
                'maxSeconds' => $this->maxSeconds,
            ],
            stopReason: $exceeded ? StopReason::TimeLimitReached : null,
        );
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Continuation\Criteria;

use Cognesy\Agents\Core\Continuation\Contracts\CanEvaluateContinuation;
use Cognesy\Agents\Core\Continuation\Contracts\CanStartExecution;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Utils\Time\ClockInterface;
use Cognesy\Utils\Time\SystemClock;
use DateTimeImmutable;

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

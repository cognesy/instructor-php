<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Continuation\Criteria;

use Closure;
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
 * IMPORTANT: The startedAtResolver should return executionStartedAt (set at the start of each user query),
 * NOT the session creation time. This prevents timeouts in multi-turn conversations spanning days.
 *
 * Example usage:
 *   new ExecutionTimeLimit(120, fn($state) => $state->executionStartedAt() ?? $state->startedAt())
 *
 * Returns ForbidContinuation when time limit exceeded (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 */
final readonly class ExecutionTimeLimit implements CanEvaluateContinuation
{
    /** @var Closure(AgentState): DateTimeImmutable */
    private Closure $startedAtResolver;
    private ClockInterface $clock;
    private int $maxSeconds;

    /**
     * @param int $maxSeconds Maximum seconds allowed for a single execution.
     * @param Closure(AgentState): DateTimeImmutable $startedAtResolver Provides the execution start timestamp.
     *        Should return executionStartedAt() (per-query), not session startedAt().
     * @param ClockInterface|null $clock Optional clock for testing.
     */
    public function __construct(
        int $maxSeconds,
        callable $startedAtResolver,
        ?ClockInterface $clock = null,
    ) {
        if ($maxSeconds <= 0) {
            throw new \InvalidArgumentException('Max seconds must be greater than zero.');
        }
        $this->maxSeconds = $maxSeconds;
        $this->startedAtResolver = Closure::fromCallable($startedAtResolver);
        $this->clock = $clock ?? new SystemClock();
    }

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
        $startedAt = ($this->startedAtResolver)($state);
        $now = $this->clock->now();
        $elapsedSeconds = $now->getTimestamp() - $startedAt->getTimestamp();
        $exceeded = $elapsedSeconds >= $this->maxSeconds;

        $decision = $exceeded
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowContinuation;

        $reason = $exceeded
            ? sprintf('Execution time limit reached: %ds/%ds', $elapsedSeconds, $this->maxSeconds)
            : sprintf('Execution time under limit: %ds/%ds', $elapsedSeconds, $this->maxSeconds);

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

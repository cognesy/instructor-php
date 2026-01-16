<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Utils\Time\ClockInterface;
use Cognesy\Utils\Time\SystemClock;
use DateTimeImmutable;

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
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
final readonly class ExecutionTimeLimit implements CanDecideToContinue
{
    /** @var Closure(TState): DateTimeImmutable */
    private Closure $startedAtResolver;
    private ClockInterface $clock;
    private int $maxSeconds;

    /**
     * @param int $maxSeconds Maximum seconds allowed for a single execution.
     * @param Closure(TState): DateTimeImmutable $startedAtResolver Provides the execution start timestamp.
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

    /**
     * @param TState $state
     */
    #[\Override]
    public function decide(object $state): ContinuationDecision {
        /** @var TState $state */
        $startedAt = ($this->startedAtResolver)($state);
        $now = $this->clock->now();
        $elapsedSeconds = $now->getTimestamp() - $startedAt->getTimestamp();

        // Under limit: allow continuation (guard permits)
        // At/over limit: forbid continuation (guard denies)
        return $elapsedSeconds < $this->maxSeconds
            ? ContinuationDecision::AllowContinuation
            : ContinuationDecision::ForbidContinuation;
    }
}

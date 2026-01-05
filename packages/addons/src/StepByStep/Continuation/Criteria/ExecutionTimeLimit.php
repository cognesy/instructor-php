<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Utils\Time\ClockInterface;
use Cognesy\Utils\Time\SystemClock;
use DateTimeImmutable;

/**
 * Guard: Forbids continuation once elapsed time between the state's start and current clock exceeds the limit.
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
     * @param Closure(TState): DateTimeImmutable $startedAtResolver Provides the process start timestamp.
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

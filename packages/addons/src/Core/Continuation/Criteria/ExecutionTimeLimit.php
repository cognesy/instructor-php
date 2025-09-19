<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Continuation\Criteria;

use Closure;
use Cognesy\Addons\Core\Continuation\CanDecideToContinue;
use Cognesy\Utils\Time\ClockInterface;
use Cognesy\Utils\Time\SystemClock;
use DateTimeImmutable;

/**
 * Stops once elapsed time between the state's start and current clock exceeds the limit.
 *
 * @template TState of object
 */
final readonly class ExecutionTimeLimit implements CanDecideToContinue
{
    /** @var Closure(TState): DateTimeImmutable */
    private Closure $startedAtResolver;

    /**
     * @param Closure(TState): DateTimeImmutable $startedAtResolver Provides the process start timestamp.
     */
    public function __construct(
        private int $maxSeconds,
        callable $startedAtResolver,
        private ClockInterface $clock = new SystemClock(),
    ) {
        $this->startedAtResolver = Closure::fromCallable($startedAtResolver);
    }

    public function canContinue(object $state): bool {
        $startedAt = ($this->startedAtResolver)($state);
        $now = $this->clock->now();
        return ($now->getTimestamp() - $startedAt->getTimestamp()) < $this->maxSeconds;
    }
}

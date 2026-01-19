<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

/**
 * Guard: Forbids continuation once cumulative execution time exceeds the limit.
 *
 * @template TState of object
 * @implements CanEvaluateContinuation<TState>
 */
final readonly class CumulativeExecutionTimeLimit implements CanEvaluateContinuation
{
    /** @var Closure(TState): float */
    private Closure $cumulativeTimeResolver;
    private int $maxSeconds;

    /**
     * @param int $maxSeconds Maximum cumulative seconds allowed.
     * @param callable(TState): float $cumulativeTimeResolver Resolves total execution time.
     */
    public function __construct(int $maxSeconds, callable $cumulativeTimeResolver) {
        if ($maxSeconds <= 0) {
            throw new \InvalidArgumentException('Max seconds must be greater than zero.');
        }
        $this->maxSeconds = $maxSeconds;
        $this->cumulativeTimeResolver = Closure::fromCallable($cumulativeTimeResolver);
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function evaluate(object $state): ContinuationEvaluation {
        /** @var TState $state */
        $cumulativeSeconds = ($this->cumulativeTimeResolver)($state);
        $exceeded = $cumulativeSeconds >= $this->maxSeconds;

        $decision = $exceeded
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowContinuation;

        $reason = $exceeded
            ? sprintf('Cumulative execution time %.1fs exceeded limit %ds', $cumulativeSeconds, $this->maxSeconds)
            : sprintf('Cumulative execution time %.1fs under limit %ds', $cumulativeSeconds, $this->maxSeconds);

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: [
                'cumulativeSeconds' => $cumulativeSeconds,
                'maxSeconds' => $this->maxSeconds,
            ],
            stopReason: $exceeded ? StopReason::TimeLimitReached : null,
        );
    }
}

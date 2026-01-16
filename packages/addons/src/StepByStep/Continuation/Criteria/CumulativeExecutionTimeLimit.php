<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\CanExplainContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;

/**
 * Guard: Forbids continuation once cumulative execution time exceeds the limit.
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
final readonly class CumulativeExecutionTimeLimit implements CanDecideToContinue, CanExplainContinuation
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
    public function decide(object $state): ContinuationDecision {
        return $this->explain($state)->decision;
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function explain(object $state): ContinuationEvaluation {
        /** @var TState $state */
        $cumulativeSeconds = ($this->cumulativeTimeResolver)($state);
        $exceeded = $cumulativeSeconds >= $this->maxSeconds;

        $decision = ContinuationDecision::AllowContinuation;
        $reason = sprintf(
            'Cumulative execution time %.1fs under limit %ds',
            $cumulativeSeconds,
            $this->maxSeconds,
        );

        if ($exceeded) {
            $decision = ContinuationDecision::ForbidContinuation;
            $reason = sprintf(
                'Cumulative execution time %.1fs exceeded limit %ds',
                $cumulativeSeconds,
                $this->maxSeconds,
            );
        }

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: [
                'cumulativeSeconds' => $cumulativeSeconds,
                'maxSeconds' => $this->maxSeconds,
            ],
        );
    }
}

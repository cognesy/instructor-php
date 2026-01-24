<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Continuation\Criteria;

use Closure;
use Cognesy\Agents\Agent\Continuation\CanEvaluateContinuation;
use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Continuation\ContinuationEvaluation;
use Cognesy\Agents\Agent\Continuation\StopReason;
use Cognesy\Agents\Agent\Data\AgentState;

/**
 * Guard: Forbids continuation once cumulative execution time exceeds the limit.
 */
final readonly class CumulativeExecutionTimeLimit implements CanEvaluateContinuation
{
    /** @var Closure(AgentState): float */
    private Closure $cumulativeTimeResolver;
    private int $maxSeconds;

    /**
     * @param int $maxSeconds Maximum cumulative seconds allowed.
     * @param callable(AgentState): float $cumulativeTimeResolver Resolves total execution time.
     */
    public function __construct(int $maxSeconds, callable $cumulativeTimeResolver) {
        if ($maxSeconds <= 0) {
            throw new \InvalidArgumentException('Max seconds must be greater than zero.');
        }
        $this->maxSeconds = $maxSeconds;
        $this->cumulativeTimeResolver = Closure::fromCallable($cumulativeTimeResolver);
    }

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
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

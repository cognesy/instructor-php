<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

/**
 * Guard: Forbids continuation when accumulated token usage reaches or exceeds the configured limit.
 *
 * Returns ForbidContinuation when limit exceeded (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 *
 * @template TState of object
 * @implements CanEvaluateContinuation<TState>
 */
final readonly class TokenUsageLimit implements CanEvaluateContinuation
{
    /** @var Closure(TState): int */
    private Closure $usageCounter;

    /**
     * @param Closure(TState): int $usageCounter Produces the total token usage for the state.
     */
    public function __construct(
        private int $maxTokens,
        callable $usageCounter,
    ) {
        $this->usageCounter = Closure::fromCallable($usageCounter);
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function evaluate(object $state): ContinuationEvaluation {
        /** @var TState $state */
        $currentUsage = ($this->usageCounter)($state);
        $exceeded = $currentUsage >= $this->maxTokens;

        $decision = $exceeded
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowContinuation;

        $reason = $exceeded
            ? sprintf('Token limit reached: %d/%d', $currentUsage, $this->maxTokens)
            : sprintf('Tokens under limit: %d/%d', $currentUsage, $this->maxTokens);

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: [
                'currentUsage' => $currentUsage,
                'maxTokens' => $this->maxTokens,
            ],
            stopReason: $exceeded ? StopReason::TokenLimitReached : null,
        );
    }
}

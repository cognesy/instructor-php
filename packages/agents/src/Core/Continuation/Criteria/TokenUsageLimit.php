<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Continuation\Criteria;

use Closure;
use Cognesy\Agents\Core\Continuation\Contracts\CanEvaluateContinuation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Guard: Forbids continuation when accumulated token usage reaches or exceeds the configured limit.
 *
 * Returns ForbidContinuation when limit exceeded (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 */
final readonly class TokenUsageLimit implements CanEvaluateContinuation
{
    /** @var Closure(AgentState): int */
    private Closure $usageCounter;

    /**
     * @param Closure(AgentState): int $usageCounter Produces the total token usage for the state.
     */
    public function __construct(
        private int $maxTokens,
        callable $usageCounter,
    ) {
        $this->usageCounter = Closure::fromCallable($usageCounter);
    }

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
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

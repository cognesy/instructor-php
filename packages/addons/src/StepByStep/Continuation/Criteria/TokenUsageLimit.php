<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;

/**
 * Guard: Forbids continuation when accumulated token usage reaches or exceeds the configured limit.
 *
 * Returns ForbidContinuation when limit exceeded (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
final readonly class TokenUsageLimit implements CanDecideToContinue
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
    public function decide(object $state): ContinuationDecision {
        /** @var TState $state */
        $currentUsage = ($this->usageCounter)($state);

        // Under limit: allow continuation (guard permits)
        // At/over limit: forbid continuation (guard denies)
        return $currentUsage < $this->maxTokens
            ? ContinuationDecision::AllowContinuation
            : ContinuationDecision::ForbidContinuation;
    }
}

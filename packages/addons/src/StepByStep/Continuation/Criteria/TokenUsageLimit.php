<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;

/**
 * Stops when accumulated token usage reaches or exceeds the configured limit.
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
    public function canContinue(object $state): bool {
        /** @var TState $state */
        return ($this->usageCounter)($state) < $this->maxTokens;
    }
}

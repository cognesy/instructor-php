<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Continuation\Criteria;

use Closure;
use Cognesy\Addons\Core\Continuation\CanDecideToContinue;

/**
 * Stops when accumulated token usage reaches or exceeds the configured limit.
 *
 * @template TState of object
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

    public function canContinue(object $state): bool {
        return ($this->usageCounter)($state) < $this->maxTokens;
    }
}

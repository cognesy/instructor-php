<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Continuation;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;

/**
 * Stops when the current step does not contain any tool calls.
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
final readonly class ToolCallPresenceCheck implements CanDecideToContinue
{
    /** @var Closure(TState): bool */
    private Closure $hasToolCallsResolver;

    /**
     * @param callable(TState): bool $hasToolCallsResolver
     */
    public function __construct(callable $hasToolCallsResolver) {
        $this->hasToolCallsResolver = Closure::fromCallable($hasToolCallsResolver);
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function canContinue(object $state): bool {
        /** @var TState $state */
        return ($this->hasToolCallsResolver)($state);
    }
}

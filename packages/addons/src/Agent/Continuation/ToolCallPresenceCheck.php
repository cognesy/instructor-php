<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Continuation;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;

/**
 * Work driver: Requests continuation when the current step contains tool calls.
 *
 * Returns RequestContinuation when tool calls are present (has work to do),
 * AllowStop when no tool calls (work complete from this criterion's perspective).
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
    public function decide(object $state): ContinuationDecision {
        /** @var TState $state */
        $hasToolCalls = ($this->hasToolCallsResolver)($state);

        return $hasToolCalls
            ? ContinuationDecision::RequestContinuation
            : ContinuationDecision::AllowStop;
    }
}

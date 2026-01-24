<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Continuation;

use Closure;
use Cognesy\Agents\Agent\Data\AgentState;

/**
 * Work driver: Requests continuation when the current step contains tool calls.
 *
 * Returns RequestContinuation when tool calls are present (has work to do),
 * AllowStop when no tool calls (work complete from this criterion's perspective).
 */
final readonly class ToolCallPresenceCheck implements CanEvaluateContinuation
{
    /** @var Closure(AgentState): bool */
    private Closure $hasToolCallsResolver;

    /**
     * @param callable(AgentState): bool $hasToolCallsResolver
     */
    public function __construct(callable $hasToolCallsResolver) {
        $this->hasToolCallsResolver = Closure::fromCallable($hasToolCallsResolver);
    }

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
        $hasToolCalls = ($this->hasToolCallsResolver)($state);

        $decision = $hasToolCalls
            ? ContinuationDecision::RequestContinuation
            : ContinuationDecision::AllowStop;

        $reason = $hasToolCalls
            ? 'Tool calls present, requesting continuation'
            : 'No tool calls, allowing stop';

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: ['hasToolCalls' => $hasToolCalls],
        );
    }
}

<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Core\Continuation;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;

/**
 * Work driver: Requests continuation when the current step contains tool calls.
 *
 * Returns RequestContinuation when tool calls are present (has work to do),
 * AllowStop when no tool calls (work complete from this criterion's perspective).
 *
 * @template TState of object
 * @implements CanEvaluateContinuation<TState>
 */
final readonly class ToolCallPresenceCheck implements CanEvaluateContinuation
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
    public function evaluate(object $state): ContinuationEvaluation {
        /** @var TState $state */
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

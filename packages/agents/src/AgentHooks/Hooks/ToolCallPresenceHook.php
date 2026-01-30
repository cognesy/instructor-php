<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Closure;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Hook adapter for tool-call presence continuation checks.
 */
final readonly class ToolCallPresenceHook implements Hook
{
    /** @var Closure(AgentState): bool */
    private Closure $hasToolCallsResolver;

    /**
     * @param callable(AgentState): bool $hasToolCallsResolver
     */
    public function __construct(callable $hasToolCallsResolver)
    {
        $this->hasToolCallsResolver = Closure::fromCallable($hasToolCallsResolver);
    }

    #[\Override]
    public function appliesTo(): array
    {
        return [HookType::AfterStep];
    }

    #[\Override]
    public function process(AgentState $state, HookType $event): AgentState
    {
        $hasToolCalls = ($this->hasToolCallsResolver)($state);

        $decision = $hasToolCalls
            ? ContinuationDecision::RequestContinuation
            : ContinuationDecision::AllowStop;

        $reason = $hasToolCalls
            ? 'Tool calls present, requesting continuation'
            : 'No tool calls, allowing stop';

        return $state->withEvaluation(new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: ['hasToolCalls' => $hasToolCalls],
        ));
    }
}

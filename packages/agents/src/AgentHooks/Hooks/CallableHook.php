<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Closure;
use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Simple hook that wraps a callable function.
 *
 * The callable receives AgentState and HookType, and returns AgentState.
 * Use this to create custom hooks without implementing the full interface.
 *
 * @example
 * // Log step events
 * $hook = new CallableHook(
 *     events: [HookType::BeforeStep, HookType::AfterStep],
 *     callback: function (AgentState $state, HookType $event): AgentState {
 *         $this->logger->info("Event: {$event->value}");
 *         return $state;
 *     },
 * );
 *
 * @example
 * // Guard hook that writes evaluations
 * $hook = new CallableHook(
 *     events: [HookType::BeforeStep],
 *     callback: function (AgentState $state, HookType $event): AgentState {
 *         if ($state->stepCount() >= 10) {
 *             return $state->withEvaluation(
 *                 ContinuationEvaluation::fromDecision(
 *                     self::class,
 *                     ContinuationDecision::ForbidContinuation,
 *                     StopReason::StepsLimitReached,
 *                 )
 *             );
 *         }
 *         return $state;
 *     },
 * );
 */
final readonly class CallableHook implements Hook
{
    /** @var Closure(AgentState, HookType): AgentState */
    private Closure $callback;

    /**
     * @param list<HookType> $events Event types this hook handles
     * @param callable(AgentState, HookType): AgentState $callback The processing function
     */
    public function __construct(
        private array $events,
        callable $callback,
    ) {
        $this->callback = Closure::fromCallable($callback);
    }

    #[\Override]
    public function appliesTo(): array
    {
        return $this->events;
    }

    #[\Override]
    public function process(AgentState $state, HookType $event): AgentState
    {
        return ($this->callback)($state, $event);
    }
}

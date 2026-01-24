<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing;

use Closure;
use Cognesy\Agents\Agent\Data\AgentState;

/**
 * Middleware chain for processing AgentState.
 */
final readonly class StateProcessors implements CanApplyProcessors
{
    /** @var CanProcessAgentState[] */
    protected array $processors;

    public function __construct(CanProcessAgentState ...$processors) {
        $this->processors = $processors;
    }

    public static function empty(): static {
        return new static();
    }

    public function withProcessors(CanProcessAgentState ...$processors): static {
        return new static(...$processors);
    }

    #[\Override]
    public function apply(AgentState $state, ?callable $terminalFn = null): AgentState {
        $middlewareChain = $this->buildMiddlewareChain($terminalFn);
        return $middlewareChain($state);
    }

    /**
     * @param (callable(AgentState): AgentState)|null $terminalFn
     * @return Closure(AgentState): AgentState
     */
    private function buildMiddlewareChain(?callable $terminalFn = null): Closure {
        $next = $terminalFn !== null
            ? Closure::fromCallable($terminalFn)
            : static fn(AgentState $state): AgentState => $state;

        foreach (array_reverse($this->processors) as $processor) {
            $currentNext = $next;
            $next = static function(AgentState $state) use ($processor, $currentNext): AgentState {
                if (!$processor->canProcess($state)) {
                    return $currentNext($state);
                }
                return $processor->process($state, $currentNext);
            };
        }

        return $next;
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing\Processors;

use Closure;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;

final class CallableProcessor implements CanProcessAgentState
{
    /** @var Closure(AgentState): AgentState */
    private Closure $processor;

    /**
     * @param callable(AgentState): AgentState $processor
     */
    public function __construct(callable $processor) {
        $this->processor = $processor(...);
    }

    #[\Override]
    public function canProcess(AgentState $state): bool {
        return true;
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = ($this->processor)($state);
        return $next ? $next($newState) : $newState;
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing\Processors;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;

final class ClearExecutionBuffer implements CanProcessAgentState
{
    #[\Override]
    public function canProcess(AgentState $state): bool {
        return true;
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next ? $next($state) : $state;
        $outcome = $newState->continuationOutcome();
        if ($outcome === null) {
            return $newState;
        }
        if ($outcome->shouldContinue()) {
            return $newState;
        }

        $store = $newState->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->clear();

        return $newState->withMessageStore($store);
    }
}

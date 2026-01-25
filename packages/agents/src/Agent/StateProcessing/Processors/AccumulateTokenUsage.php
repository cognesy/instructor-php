<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing\Processors;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;

final class AccumulateTokenUsage implements CanProcessAgentState
{
    #[\Override]
    public function canProcess(AgentState $state): bool {
        return true;
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        return $next ? $next($state) : $state;
    }
}

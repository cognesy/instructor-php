<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing\Processors;

use Cognesy\Agents\Agent\Contracts\CanMakeNextStep;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;

class GenerateNextStep implements CanProcessAgentState
{
    public function __construct(
        private CanMakeNextStep $nextStepGenerator,
    ) {}

    #[\Override]
    public function canProcess(AgentState $state): bool {
        return true;
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $nextStep = $this->nextStepGenerator->makeNextStep($state);
        $newState = $state->recordStep($nextStep);
        return $next ? $next($newState) : $newState;
    }
}

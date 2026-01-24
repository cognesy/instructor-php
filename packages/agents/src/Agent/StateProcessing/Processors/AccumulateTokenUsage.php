<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing\Processors;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Polyglot\Inference\Data\Usage;

final class AccumulateTokenUsage implements CanProcessAgentState
{
    #[\Override]
    public function canProcess(AgentState $state): bool {
        return true;
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next ? $next($state) : $state;

        $step = $newState->currentStep();
        $usage = $step instanceof AgentStep ? $step->usage() : Usage::none();

        return $newState->withAccumulatedUsage($usage);
    }
}

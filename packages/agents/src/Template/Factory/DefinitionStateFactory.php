<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Factory;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentState;
use Cognesy\Agents\Template\Data\AgentDefinition;

final readonly class DefinitionStateFactory implements CanInstantiateAgentState
{
    #[\Override]
    public function instantiateAgentState(
        AgentDefinition $definition,
        ?AgentState $seed = null,
    ): AgentState {
        $state = $seed ?? AgentState::empty();
        $state = $this->withSystemPrompt($state, $definition);
        $state = $this->withMetadata($state, $definition);
        return $this->withBudget($state, $definition);
    }

    // INTERNALS ////////////////////////////////////////////////////

    private function withSystemPrompt(AgentState $state, AgentDefinition $definition): AgentState {
        if (trim($definition->systemPrompt) === '') {
            return $state;
        }

        return $state->withSystemPrompt($definition->systemPrompt);
    }

    private function withMetadata(AgentState $state, AgentDefinition $definition): AgentState {
        if ($definition->metadata === null || $definition->metadata->isEmpty()) {
            return $state;
        }

        $mergedMetadata = $state->metadata()->withMergedData($definition->metadata->toArray());
        return $state->with(context: $state->context()->withMetadata($mergedMetadata));
    }

    private function withBudget(AgentState $state, AgentDefinition $definition): AgentState {
        $definitionBudget = $definition->budget();

        if ($definitionBudget->isEmpty()) {
            return $state;
        }

        return $state->withBudget($state->budget()->cappedBy($definitionBudget));
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Hook that appends execution metadata to the agent state after each step.
 *
 * Records step timing and usage information for observability.
 */
final readonly class AppendContextMetadataHook implements Hook
{
    #[\Override]
    public function appliesTo(): array
    {
        return [HookType::AfterStep];
    }

    #[\Override]
    public function process(AgentState $state, HookType $event): AgentState
    {
        $currentStep = $state->currentStep();
        if ($currentStep === null) {
            return $state;
        }

        // Record step completion metadata
        $usage = $currentStep->usage();
        $stepType = $currentStep->stepType();

        return $state
            ->withMetadata('last_step_type', $stepType->value)
            ->withMetadata('last_step_tokens', $usage->total());
    }
}

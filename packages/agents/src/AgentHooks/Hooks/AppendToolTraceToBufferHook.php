<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Enums\AgentStepType;

/**
 * Hook that appends tool execution trace to the execution buffer after each step.
 *
 * Tool execution messages go to the execution buffer (not main conversation) so
 * they can be summarized or managed separately.
 */
final readonly class AppendToolTraceToBufferHook implements Hook
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

        // Only process tool execution steps
        if ($currentStep->stepType() !== AgentStepType::ToolExecution) {
            return $state;
        }

        $outputMessages = $currentStep->outputMessages();
        if ($outputMessages->isEmpty()) {
            return $state;
        }

        $store = $state->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->appendMessages($outputMessages);

        return $state->withMessageStore($store);
    }
}

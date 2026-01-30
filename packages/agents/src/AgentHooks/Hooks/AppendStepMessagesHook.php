<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Hook that appends the current step's messages to the conversation history.
 */
final readonly class AppendStepMessagesHook implements Hook
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

        $outputMessages = $currentStep->outputMessages();
        if ($outputMessages->isEmpty()) {
            return $state;
        }

        $store = $state->store()
            ->section(AgentState::DEFAULT_SECTION)
            ->appendMessages($outputMessages);

        return $state->withMessageStore($store);
    }
}

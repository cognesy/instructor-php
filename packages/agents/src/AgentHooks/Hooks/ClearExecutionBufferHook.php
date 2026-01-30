<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Hook that clears the execution buffer after each step.
 */
final readonly class ClearExecutionBufferHook implements Hook
{
    #[\Override]
    public function appliesTo(): array
    {
        return [HookType::AfterStep];
    }

    #[\Override]
    public function process(AgentState $state, HookType $event): AgentState
    {
        $store = $state->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->clear();

        return $state->withMessageStore($store);
    }
}

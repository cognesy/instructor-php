<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Metadata;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Hook that persists MetadataWriteResult to agent state.
 *
 * Runs after each step, inspects tool executions for successful
 * store_metadata calls, and updates the agent's metadata accordingly.
 */
final readonly class PersistMetadataHook implements Hook
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

        $toolExecutions = $currentStep->toolExecutions();
        if (!$toolExecutions->hasExecutions()) {
            return $state;
        }

        $metadata = $state->metadata();
        $changed = false;

        foreach ($toolExecutions->all() as $execution) {
            if ($execution->toolCall()->name() !== MetadataWriteTool::TOOL_NAME) {
                continue;
            }

            if ($execution->hasError()) {
                continue;
            }

            $result = $execution->value();

            // Handle MetadataWriteResult object
            if ($result instanceof MetadataWriteResult) {
                if ($result->success) {
                    $metadata = $metadata->withKeyValue($result->key, $result->value);
                    $changed = true;
                }
                continue;
            }

            // Handle array result (from serialization/deserialization)
            if (is_array($result) && ($result['success'] ?? false) === true) {
                $key = $result['key'] ?? null;
                $value = $result['value'] ?? null;

                if ($key !== null) {
                    $metadata = $metadata->withKeyValue($key, $value);
                    $changed = true;
                }
            }
        }

        if ($changed) {
            return $state->with(variables: $metadata);
        }

        return $state;
    }
}

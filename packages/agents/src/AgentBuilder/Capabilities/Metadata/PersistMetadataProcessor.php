<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Metadata;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;

/**
 * Processor that persists MetadataWriteResult to agent state.
 *
 * Runs after each step, inspects tool executions for successful
 * store_metadata calls, and updates the agent's metadata accordingly.
 */
class PersistMetadataProcessor implements CanProcessAgentState
{
    #[\Override]
    public function canProcess(AgentState $state): bool {
        return true;
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next !== null ? $next($state) : $state;

        $currentStep = $newState->currentStep();
        if ($currentStep === null) {
            return $newState;
        }

        $toolExecutions = $currentStep->toolExecutions();
        if (!$toolExecutions->hasExecutions()) {
            return $newState;
        }

        $metadata = $newState->metadata();

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
                }
                continue;
            }

            // Handle array result (from serialization/deserialization)
            if (is_array($result) && ($result['success'] ?? false) === true) {
                $key = $result['key'] ?? null;
                $value = $result['value'] ?? null;

                if ($key !== null) {
                    $metadata = $metadata->withKeyValue($key, $value);
                }
            }
        }

        if ($metadata !== $newState->metadata()) {
            $newState = $newState->with(variables: $metadata);
        }

        return $newState;
    }
}

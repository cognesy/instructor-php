<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\Metadata;

use Cognesy\Addons\Agent\Core\Contracts\HasStepToolExecutions;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;

/**
 * Processor that persists MetadataWriteResult to agent state.
 *
 * Runs after each step, inspects tool executions for successful
 * store_metadata calls, and updates the agent's metadata accordingly.
 *
 * @implements CanProcessAnyState<AgentState>
 */
class PersistMetadataProcessor implements CanProcessAnyState
{
    #[\Override]
    public function canProcess(object $state): bool {
        return $state instanceof AgentState;
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object {
        $newState = $next !== null ? $next($state) : $state;

        assert($newState instanceof AgentState);

        $currentStep = $newState->currentStep();
        if ($currentStep === null) {
            return $newState;
        }

        if (!($currentStep instanceof HasStepToolExecutions)) {
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

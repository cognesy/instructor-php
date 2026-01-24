<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\StructuredOutput;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;

/**
 * Processor that persists successful structured output extractions to agent state.
 *
 * When a structured_output tool call includes a 'store_as' parameter,
 * this processor stores the extracted data in agent metadata under that key.
 */
class PersistStructuredOutputProcessor implements CanProcessAgentState
{
    #[\Override]
    public function canProcess(AgentState $state): bool {
        return true;
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next !== null ? $next($state) : $state;

        $currentStep = $newState->currentStep();
        if (!($currentStep instanceof AgentStep)) {
            return $newState;
        }

        $toolExecutions = $currentStep->toolExecutions();
        if (!$toolExecutions->hasExecutions()) {
            return $newState;
        }

        $metadata = $newState->metadata();

        foreach ($toolExecutions->all() as $execution) {
            if ($execution->toolCall()->name() !== StructuredOutputTool::TOOL_NAME) {
                continue;
            }

            if ($execution->hasError()) {
                continue;
            }

            $result = $execution->value();

            // Handle StructuredOutputResult object
            if ($result instanceof StructuredOutputResult) {
                if ($result->success && $result->storeAs !== null) {
                    $metadata = $metadata->withKeyValue($result->storeAs, $result->data);
                }
                continue;
            }

            // Handle array result (from serialization/deserialization)
            if (is_array($result)) {
                $success = $result['success'] ?? false;
                $storeAs = $result['store_as'] ?? null;
                $data = $result['data'] ?? null;

                if ($success && $storeAs !== null) {
                    $metadata = $metadata->withKeyValue($storeAs, $data);
                }
            }
        }

        if ($metadata !== $newState->metadata()) {
            $newState = $newState->with(variables: $metadata);
        }

        return $newState;
    }
}

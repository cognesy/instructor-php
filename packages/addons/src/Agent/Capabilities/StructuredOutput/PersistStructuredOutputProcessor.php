<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\StructuredOutput;

use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Step\HasStepToolExecutions;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;

/**
 * Processor that persists successful structured output extractions to agent state.
 *
 * When a structured_output tool call includes a 'store_as' parameter,
 * this processor stores the extracted data in agent metadata under that key.
 *
 * @implements CanProcessAnyState<AgentState>
 */
class PersistStructuredOutputProcessor implements CanProcessAnyState
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

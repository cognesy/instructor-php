<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\StructuredOutput;

use Cognesy\Agents\Hooks\Contracts\HookInterface;
use Cognesy\Agents\Hooks\Data\HookContext;

/**
 * Hook that persists successful structured output extractions to agent state.
 *
 * When a structured_output tool call includes a 'store_as' parameter,
 * this hook stores the extracted data in agent metadata under that key.
 */
final readonly class PersistStructuredOutputHook implements HookInterface
{
    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        $currentStep = $state->currentStep();

        if ($currentStep === null) {
            return $context;
        }

        $toolExecutions = $currentStep->toolExecutions();
        if (!$toolExecutions->hasExecutions()) {
            return $context;
        }

        $metadata = $state->metadata();
        $changed = false;

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
                    $changed = true;
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
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $nextState = $state->with(context: $state->context()->withMetadata($metadata));
            return $context->withState($nextState);
        }

        return $context;
    }
}

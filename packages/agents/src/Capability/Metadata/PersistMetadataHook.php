<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Metadata;

use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;

/**
 * Hook that persists MetadataWriteResult to agent state.
 *
 * Runs after each step, inspects tool executions for successful
 * store_metadata calls, and updates the agent's metadata accordingly.
 */
final readonly class PersistMetadataHook implements HookInterface
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
            $nextState = $state->with(context: $state->context()->withMetadata($metadata));
            return $context->withState($nextState);
        }

        return $context;
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Metadata;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;
use Cognesy\Agents\AgentHooks\Data\StepHookContext;
use Cognesy\Agents\AgentHooks\Enums\HookType;

/**
 * Hook that persists MetadataWriteResult to agent state.
 *
 * Runs after each step, inspects tool executions for successful
 * store_metadata calls, and updates the agent's metadata accordingly.
 */
final readonly class PersistMetadataHook implements Hook
{
    #[\Override]
    public function handle(HookContext $context, callable $next): HookOutcome
    {
        if (!$context instanceof StepHookContext || $context->eventType() !== HookType::AfterStep) {
            return $next($context);
        }

        $state = $context->state();
        $currentStep = $state->currentStep();

        if ($currentStep === null) {
            return $next($context);
        }

        $toolExecutions = $currentStep->toolExecutions();
        if (!$toolExecutions->hasExecutions()) {
            return $next($context);
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
            $newState = $state->with(variables: $metadata);
            return $next($context->withState($newState));
        }

        return $next($context);
    }
}

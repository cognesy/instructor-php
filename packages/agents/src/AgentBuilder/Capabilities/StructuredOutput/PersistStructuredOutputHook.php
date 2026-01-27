<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\StructuredOutput;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;
use Cognesy\Agents\AgentHooks\Data\StepHookContext;
use Cognesy\Agents\AgentHooks\Enums\HookType;

/**
 * Hook that persists successful structured output extractions to agent state.
 *
 * When a structured_output tool call includes a 'store_as' parameter,
 * this hook stores the extracted data in agent metadata under that key.
 */
final readonly class PersistStructuredOutputHook implements Hook
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
            $newState = $state->with(variables: $metadata);
            return $next($context->withState($newState));
        }

        return $next($context);
    }
}

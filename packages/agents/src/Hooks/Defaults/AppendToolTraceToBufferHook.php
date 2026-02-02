<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Defaults;

use Cognesy\Agents\Core\Context\AgentContext;
use Cognesy\Agents\Core\Enums\AgentStepType;
use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\HookInterface;

final readonly class AppendToolTraceToBufferHook implements HookInterface
{
    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        $currentStep = $state->currentStep();
        if ($currentStep === null) {
            return $context;
        }

        if ($currentStep->stepType() !== AgentStepType::ToolExecution) {
            return $context;
        }

        $outputMessages = $currentStep->outputMessages();
        if ($outputMessages->isEmpty()) {
            return $context;
        }

        $store = $state->store()
            ->section(AgentContext::EXECUTION_BUFFER_SECTION)
            ->appendMessages($outputMessages);

        return $context->withState($state->withMessageStore($store));
    }
}

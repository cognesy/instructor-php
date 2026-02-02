<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Defaults;

use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\HookInterface;

final readonly class AppendContextMetadataHook implements HookInterface
{
    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        $currentStep = $state->currentStep();
        if ($currentStep === null) {
            return $context;
        }

        $usage = $currentStep->usage();
        $stepType = $currentStep->stepType();

        $nextState = $state
            ->withMetadata('last_step_type', $stepType->value)
            ->withMetadata('last_step_tokens', $usage->total());

        return $context->withState($nextState);
    }
}

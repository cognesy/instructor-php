<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinueToolUse;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Enums\StepType;

final class StopOnFinalDecision implements CanDecideToContinueToolUse
{
    public function canContinue(ToolUseState $state): bool {
        $type = $state->currentStep()?->stepType();
        return match(true) {
            $type === null => true,
            StepType::ToolExecution->is($type) => true,
            default => false
        };
    }
}


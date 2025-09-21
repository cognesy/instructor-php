<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct;

use Cognesy\Addons\Core\Continuation\CanDecideToContinue;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Enums\ToolUseStepType;

final class StopOnFinalDecision implements CanDecideToContinue
{
    public function canContinue(object $state): bool {
        if (!$state instanceof ToolUseState) {
            return true;
        }

        $type = $state->currentStep()?->stepType();
        return match(true) {
            $type === null => true,
            ToolUseStepType::ToolExecution->is($type) => true,
            default => false
        };
    }
}

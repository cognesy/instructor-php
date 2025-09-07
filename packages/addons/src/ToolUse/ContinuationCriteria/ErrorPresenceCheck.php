<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinueToolUse;
use Cognesy\Addons\ToolUse\Data\ToolUseState;

class ErrorPresenceCheck implements CanDecideToContinueToolUse
{
    public function canContinue(ToolUseState $state): bool {
        $hasErrors = $state->currentStep()?->hasErrors() ?? false;
        return !($hasErrors);
    }
}
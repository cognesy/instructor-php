<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\ToolUseState;

class ToolCallPresenceCheck implements CanDecideToContinue
{
    public function canContinue(ToolUseState $state): bool {
        return $state->currentStep()?->hasToolCalls() ?? false;
    }
}
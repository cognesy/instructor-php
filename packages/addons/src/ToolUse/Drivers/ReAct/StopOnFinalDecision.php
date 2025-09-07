<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinueToolUse;
use Cognesy\Addons\ToolUse\Data\ToolUseState;

final class StopOnFinalDecision implements CanDecideToContinueToolUse
{
    public function canContinue(ToolUseState $state): bool {
        $current = $state->variable('react_last_decision_type', '');
        if ($current === 'final_answer') {
            return false;
        }
        return true;
    }
}


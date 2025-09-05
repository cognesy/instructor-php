<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\Data\ToolUseState;

final class StopOnFinalDecision implements CanDecideToContinue
{
    public function canContinue(ToolUseState $state): bool {
        $current = $state->variable('react_last_decision_type', '');
        if ($current === 'final_answer') {
            return false;
        }
        return true;
    }
}


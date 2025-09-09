<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\ToolUse\Contracts\CanProcessToolState;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;

class AppendToolStateMessages implements CanProcessToolState
{
    public function processStep(ToolUseStep $step, ToolUseState $state): ToolUseState {
        return $state->appendMessages($step->messages());
    }
}
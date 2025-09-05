<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;

interface ToolUseObserver
{
    public function onStepStart(ToolUseState $state) : void;
    public function onStepEnd(ToolUseState $state, ToolUseStep $step) : void;
}


<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;

interface CanProcessStep
{
    public function processStep(ToolUseStep $step, ToolUseState $state): ToolUseStep;
}
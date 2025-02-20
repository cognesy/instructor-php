<?php

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\ToolUseContext;
use Cognesy\Addons\ToolUse\ToolUseStep;

interface CanProcessStep
{
    public function processStep(ToolUseStep $step, ToolUseContext $context): ToolUseStep;
}
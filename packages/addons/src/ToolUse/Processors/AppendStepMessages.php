<?php

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\ToolUse\Contracts\CanProcessStep;
use Cognesy\Addons\ToolUse\ToolUseContext;
use Cognesy\Addons\ToolUse\ToolUseStep;

class AppendStepMessages implements CanProcessStep
{
    public function processStep(ToolUseStep $step, ToolUseContext $context): ToolUseStep {
        $context->appendMessages($step->messages());
        return $step;
    }
}
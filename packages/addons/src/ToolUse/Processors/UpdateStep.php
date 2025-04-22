<?php

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\ToolUse\Contracts\CanProcessStep;
use Cognesy\Addons\ToolUse\ToolUseContext;
use Cognesy\Addons\ToolUse\ToolUseStep;

class UpdateStep implements CanProcessStep
{
    public function processStep(ToolUseStep $step, ToolUseContext $context): ToolUseStep {
        $context->addStep($step);
        $context->setCurrentStep($step);
        return $step;
    }
}
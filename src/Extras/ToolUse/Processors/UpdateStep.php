<?php

namespace Cognesy\Instructor\Extras\ToolUse\Processors;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanProcessStep;
use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;

class UpdateStep implements CanProcessStep
{
    public function processStep(ToolUseStep $step, ToolUseContext $context): ToolUseStep {
        $context->addStep($step);
        $context->setCurrentStep($step);
        return $step;
    }
}
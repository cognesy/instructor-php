<?php

namespace Cognesy\Instructor\Extras\ToolUse\Processors;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanProcessStep;
use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;

class AccumulateTokenUsage implements CanProcessStep
{
    public function processStep(ToolUseStep $step, ToolUseContext $context): ToolUseStep {
        $context->accumulateUsage($step->usage());
        return $step;
    }
}

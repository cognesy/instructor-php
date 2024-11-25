<?php

namespace Cognesy\Instructor\Extras\ToolUse\Contracts;

use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;

interface CanProcessStep
{
    public function processStep(ToolUseStep $step, ToolUseContext $context): ToolUseStep;
}
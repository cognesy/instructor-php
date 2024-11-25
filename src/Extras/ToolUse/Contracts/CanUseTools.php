<?php

namespace Cognesy\Instructor\Extras\ToolUse\Contracts;

use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;

interface CanUseTools
{
    public function useTools(ToolUseContext $context): ToolUseStep;
}

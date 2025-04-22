<?php

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\ToolUseContext;
use Cognesy\Addons\ToolUse\ToolUseStep;

interface CanUseTools
{
    public function useTools(ToolUseContext $context): ToolUseStep;
}

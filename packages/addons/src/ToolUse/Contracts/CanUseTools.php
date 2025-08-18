<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\ToolUseState;
use Cognesy\Addons\ToolUse\ToolUseStep;

interface CanUseTools
{
    public function useTools(ToolUseState $state): ToolUseStep;
}

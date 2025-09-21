<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\ToolExecutor;
use Cognesy\Addons\ToolUse\Tools;

interface CanUseTools
{
    public function useTools(ToolUseState $state, Tools $tools, ToolExecutor $executor): ToolUseStep;
}

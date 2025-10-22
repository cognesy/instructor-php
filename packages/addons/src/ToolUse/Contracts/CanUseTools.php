<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;

interface CanUseTools
{
    public function useTools(ToolUseState $state, Tools $tools, CanExecuteToolCalls $executor): ToolUseStep;
}

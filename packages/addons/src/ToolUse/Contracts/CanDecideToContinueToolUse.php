<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\Data\ToolUseState;

interface CanDecideToContinueToolUse
{
    public function canContinue(ToolUseState $state) : bool;
}

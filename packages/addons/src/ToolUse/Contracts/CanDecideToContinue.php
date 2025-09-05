<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\Data\ToolUseState;

interface CanDecideToContinue
{
    public function canContinue(ToolUseState $state) : bool;
}

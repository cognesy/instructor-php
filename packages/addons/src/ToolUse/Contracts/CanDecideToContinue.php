<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\ToolUseContext;

interface CanDecideToContinue
{
    public function canContinue(ToolUseContext $context) : bool;
}

<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\Data\ToolUseState;

interface CanProcessToolState
{
    public function process(ToolUseState $state, ?callable $next = null): ToolUseState;
}
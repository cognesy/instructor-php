<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Polyglot\Inference\Data\ToolCall;

interface ToolsObserver
{
    public function onToolStart(ToolUseState $state, ToolCall $toolCall) : void;
    public function onToolEnd(ToolUseState $state, ToolExecution $execution) : void;
}


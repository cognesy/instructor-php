<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;

interface CanExecuteToolCalls
{
    public function useTool(ToolCall $toolCall, ToolUseState $state): ToolExecution;
    public function useTools(ToolCalls $toolCalls, ToolUseState $state): ToolExecutions;
}
<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Contracts;

use Cognesy\Addons\Agent\Collections\ToolExecutions;
use Cognesy\Addons\Agent\Data\ToolExecution;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;

interface CanExecuteToolCalls
{
    public function useTool(ToolCall $toolCall, AgentState $state): ToolExecution;
    public function useTools(ToolCalls $toolCalls, AgentState $state): ToolExecutions;
}
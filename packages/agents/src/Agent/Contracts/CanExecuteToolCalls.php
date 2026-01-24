<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Contracts;

use Cognesy\Agents\Agent\Collections\ToolExecutions;
use Cognesy\Agents\Agent\Data\ToolExecution;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;

interface CanExecuteToolCalls
{
    public function useTool(ToolCall $toolCall, AgentState $state): ToolExecution;
    public function useTools(ToolCalls $toolCalls, AgentState $state): ToolExecutions;
}
<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Core\Contracts;

use Cognesy\Addons\Agent\Core\Collections\ToolExecutions;
use Cognesy\Addons\Agent\Core\Data\AgentExecution;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;

interface CanExecuteToolCalls
{
    public function useTool(ToolCall $toolCall, AgentState $state): AgentExecution;
    public function useTools(ToolCalls $toolCalls, AgentState $state): ToolExecutions;
}
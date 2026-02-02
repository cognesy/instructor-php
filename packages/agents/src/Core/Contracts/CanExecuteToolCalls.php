<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Contracts;

use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;

interface CanExecuteToolCalls
{
    public function executeTools(ToolCalls $toolCalls, AgentState $state): ToolExecutions;
}
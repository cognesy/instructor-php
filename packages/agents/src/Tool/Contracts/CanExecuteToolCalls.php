<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Contracts;

use Cognesy\Agents\Collections\ToolExecutions;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;

interface CanExecuteToolCalls
{
    public function executeTools(ToolCalls $toolCalls, AgentState $state): ToolExecutions;
}
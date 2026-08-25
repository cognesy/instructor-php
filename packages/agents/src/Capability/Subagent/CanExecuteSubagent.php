<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Subagent;

use Cognesy\Agents\Data\AgentState;

/** Optional domain-owned execution boundary for a generic subagent invocation. */
interface CanExecuteSubagent
{
    public function execute(SubagentInvocation $invocation): AgentState|SubagentExecutionResult;
}

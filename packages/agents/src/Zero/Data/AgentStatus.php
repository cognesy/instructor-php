<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Data;

enum AgentStatus : string
{
    case Ready = 'ready';             // Between executions, ready for fresh start
    case InProgress = 'in_progress';  // Execution in progress
    case Failed = 'failed';           // Execution failed
}
<?php declare(strict_types=1);

namespace Cognesy\Agents\Enums;

enum ExecutionStatus : string
{
    case Pending = 'pending';         // Between executions, ready for fresh start
    case InProgress = 'in_progress';  // Execution in progress
    case Completed = 'completed';     // Execution completed successfully
    case Stopped = 'stopped';         // Execution force-stopped by limits or external request
    case Failed = 'failed';           // Execution failed
}
<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Enums;

enum AgentStatus : string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
}
<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Core\Enums;

enum AgentStatus : string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
}
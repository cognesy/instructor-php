<?php declare(strict_types=1);

namespace Cognesy\Agents\Session;

enum SessionStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Completed = 'completed';
    case Failed = 'failed';
}

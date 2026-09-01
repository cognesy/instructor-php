<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace\Arena\Record;

enum TurnStatus: string
{
    case Completed = 'completed';
    case Stopped = 'stopped';
    case Failed = 'failed';
}

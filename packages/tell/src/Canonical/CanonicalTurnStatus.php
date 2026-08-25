<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

enum CanonicalTurnStatus: string
{
    case Completed = 'completed';
    case Stopped = 'stopped';
    case Failed = 'failed';
}

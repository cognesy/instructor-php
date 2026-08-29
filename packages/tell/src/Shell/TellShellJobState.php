<?php

declare(strict_types=1);

namespace Cognesy\Tell\Shell;

enum TellShellJobState: string
{
    case Running = 'running';
    case Exited = 'exited';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';

    public function isTerminal(): bool {
        return $this !== self::Running;
    }
}

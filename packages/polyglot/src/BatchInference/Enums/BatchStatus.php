<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Enums;

enum BatchStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Finalizing = 'finalizing';
    case Cancelling = 'cancelling';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Unknown = 'unknown';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Cancelled, self::Expired => true,
            default => false,
        };
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Instructor\Enums;

enum ExecutionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded,
            self::Failed => true,
            default => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Succeeded;
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }
}

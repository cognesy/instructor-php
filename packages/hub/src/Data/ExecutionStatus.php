<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

enum ExecutionStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case ERROR = 'error';
    case INTERRUPTED = 'interrupted';
    case SKIPPED = 'skipped';
    case STALE = 'stale';

    public function isTerminal(): bool
    {
        return match($this) {
            self::COMPLETED, self::ERROR, self::INTERRUPTED, self::SKIPPED => true,
            self::PENDING, self::RUNNING, self::STALE => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isError(): bool
    {
        return $this === self::ERROR;
    }

    public function needsExecution(): bool
    {
        return match($this) {
            self::PENDING, self::ERROR, self::INTERRUPTED, self::STALE => true,
            self::COMPLETED, self::RUNNING, self::SKIPPED => false,
        };
    }
}

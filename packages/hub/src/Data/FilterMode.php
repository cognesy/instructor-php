<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

enum FilterMode: string
{
    case ALL = 'all';
    case ERRORS_ONLY = 'errors';
    case STALE_ONLY = 'stale';
    case PENDING_ONLY = 'pending';
    case NOT_COMPLETED = 'not-completed';
    case INTERRUPTED_ONLY = 'interrupted';

    public function getDescription(): string
    {
        return match($this) {
            self::ALL => 'all examples',
            self::ERRORS_ONLY => 'examples with errors',
            self::STALE_ONLY => 'examples with outdated results',
            self::PENDING_ONLY => 'examples never executed',
            self::NOT_COMPLETED => 'examples not completed successfully',
            self::INTERRUPTED_ONLY => 'examples that were interrupted',
        };
    }

    public static function fromString(string $value): self
    {
        return match(strtolower($value)) {
            'all' => self::ALL,
            'errors', 'error' => self::ERRORS_ONLY,
            'stale', 'outdated' => self::STALE_ONLY,
            'pending', 'new' => self::PENDING_ONLY,
            'not-completed', 'incomplete' => self::NOT_COMPLETED,
            'interrupted' => self::INTERRUPTED_ONLY,
            default => self::ALL,
        };
    }
}

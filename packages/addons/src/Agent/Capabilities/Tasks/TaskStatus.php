<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\Tasks;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string {
        return match($this) {
            self::Pending => '○',
            self::InProgress => '◐',
            self::Completed => '●',
        };
    }

    public function isTerminal(): bool {
        return $this === self::Completed;
    }
}

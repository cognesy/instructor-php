<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Tasks;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Stop;

enum StopReason: string
{
    case None = 'none';
    case Completed = 'completed';
    case StepsLimitReached = 'steps_limit';
    case TokenLimitReached = 'token_limit';
    case TimeLimitReached = 'time_limit';
    case ToolBlocked = 'tool_blocked';
    case Error = 'error';
    case UserRequested = 'user_requested';
    case Unknown = 'unknown';

    public function priority(): int {
        return match ($this) {
            self::None => 0,
            self::Error => 0,
            self::ToolBlocked => 1,
            self::StepsLimitReached => 2,
            self::TokenLimitReached => 3,
            self::TimeLimitReached => 4,
            self::UserRequested => 5,
            self::Completed => 6,
            self::Unknown => 7,
        };
    }

    /**
     * Compare this StopReason with another for ordering.
     * Returns -1, 0, 1 like the spaceship operator based on priority().
     */
    public function compare(StopReason $other): int {
        return $this->priority() <=> $other->priority();
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\Continuation;

enum StopReason: string
{
    case Completed = 'completed';
    case StepsLimitReached = 'steps_limit';
    case TokenLimitReached = 'token_limit';
    case TimeLimitReached = 'time_limit';
    case RetryLimitReached = 'retry_limit';
    case ErrorForbade = 'error';
    case StopRequested = 'stop_requested';
    case FinishReasonReceived = 'finish_reason';
    case UserRequested = 'user_requested';
    case Unknown = 'unknown';

    public function priority(): int {
        return match ($this) {
            self::ErrorForbade => 0,
            self::StopRequested => 1,
            self::StepsLimitReached => 2,
            self::TokenLimitReached => 3,
            self::TimeLimitReached => 4,
            self::RetryLimitReached => 5,
            self::FinishReasonReceived => 6,
            self::UserRequested => 7,
            self::Completed => 8,
            self::Unknown => 9,
        };
    }

    public function compare(self $other): int {
        return $this->priority() <=> $other->priority();
    }
}

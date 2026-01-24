<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Continuation;

enum StopReason: string
{
    case Completed = 'completed';
    case StepsLimitReached = 'steps_limit';
    case TokenLimitReached = 'token_limit';
    case TimeLimitReached = 'time_limit';
    case RetryLimitReached = 'retry_limit';
    case ErrorForbade = 'error';
    case FinishReasonReceived = 'finish_reason';
    case GuardForbade = 'guard';
    case UserRequested = 'user_requested';
}

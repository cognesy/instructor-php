<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\ErrorHandling\Enums;

enum ErrorHandlingDecision: string
{
    case Stop = 'stop';
    case Retry = 'retry';
    case Ignore = 'ignore';
}

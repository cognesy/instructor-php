<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\ErrorHandling;

enum ErrorType: string
{
    case Tool = 'tool';
    case Model = 'model';
    case Validation = 'validation';
    case RateLimit = 'rate_limit';
    case Timeout = 'timeout';
    case Unknown = 'unknown';
}

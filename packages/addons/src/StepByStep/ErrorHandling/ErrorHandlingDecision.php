<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\ErrorHandling;

enum ErrorHandlingDecision: string
{
    case Stop = 'stop';
    case Retry = 'retry';
    case Ignore = 'ignore';
}

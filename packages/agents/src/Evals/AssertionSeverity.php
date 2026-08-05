<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

enum AssertionSeverity: string
{
    case Gate = 'gate';
    case Soft = 'soft';
}

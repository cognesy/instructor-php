<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

enum EvalExitCode: int
{
    case Success = 0;
    case EvalFailure = 1;
    case ConfigurationError = 2;
}

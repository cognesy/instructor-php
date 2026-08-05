<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

enum EvalVerdict: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Scored = 'scored';
    case Skipped = 'skipped';
}

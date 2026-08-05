<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

enum JudgeCriterion: string
{
    case Factuality = 'factuality';
    case Summarizes = 'summarizes';
    case ClosedQa = 'closed_qa';
    case Sql = 'sql';
}

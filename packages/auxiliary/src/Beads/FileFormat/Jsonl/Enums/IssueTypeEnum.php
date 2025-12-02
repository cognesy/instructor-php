<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums;

enum IssueTypeEnum: string
{
    case BUG = 'bug';
    case FEATURE = 'feature';
    case TASK = 'task';
    case EPIC = 'epic';
    case CHORE = 'chore';
}

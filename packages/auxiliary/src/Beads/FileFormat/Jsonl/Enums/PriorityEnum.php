<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums;

enum PriorityEnum: int
{
    case CRITICAL = 0;
    case HIGH = 1;
    case MEDIUM = 2;
    case LOW = 3;
    case BACKLOG = 4;
}

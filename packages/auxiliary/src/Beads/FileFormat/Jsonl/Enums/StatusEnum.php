<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums;

enum StatusEnum: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case BLOCKED = 'blocked';
    case CLOSED = 'closed';
}

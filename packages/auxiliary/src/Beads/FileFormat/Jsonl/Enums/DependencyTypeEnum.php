<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums;

enum DependencyTypeEnum: string
{
    case BLOCKS = 'blocks';
    case RELATED = 'related';
    case PARENT_CHILD = 'parent-child';
    case DISCOVERED_FROM = 'discovered-from';
}

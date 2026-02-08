<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Enums;

enum TimeoutReason: string
{
    case WALL = 'wall';
    case IDLE = 'idle';
}


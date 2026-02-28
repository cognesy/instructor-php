<?php declare(strict_types=1);

namespace Cognesy\Http\Enums;

enum StreamCachePolicy: string
{
    case None = 'none';
    case Memory = 'memory';
}


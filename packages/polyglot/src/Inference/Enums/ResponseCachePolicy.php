<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Enums;

enum ResponseCachePolicy: string
{
    case None = 'none';
    case Memory = 'memory';

    public function shouldCache(): bool
    {
        return $this === self::Memory;
    }
}

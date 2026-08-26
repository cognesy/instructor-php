<?php

declare(strict_types=1);

namespace Cognesy\Tell;

use InvalidArgumentException;

/** Provider-independent reasoning effort selected by a Tell caller. */
enum TellReasoningEffort: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public static function parse(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw new InvalidArgumentException('Reasoning effort must be one of: low, medium, high.');
    }
}

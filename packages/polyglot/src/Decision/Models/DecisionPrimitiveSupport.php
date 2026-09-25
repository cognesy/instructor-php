<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Models;

use InvalidArgumentException;

enum DecisionPrimitiveSupport: string
{
    case Native = 'native';
    case Projected = 'projected';
    case Unsupported = 'unsupported';
    case Unknown = 'unknown';

    public static function fromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }
        if ($value === null) {
            return self::Unknown;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException('Invalid Decision primitive support: expected a string.');
        }

        return self::tryFrom($value)
            ?? throw new InvalidArgumentException("Invalid Decision primitive support: {$value}");
    }

    public function isUnsupported(): bool
    {
        return $this === self::Unsupported;
    }
}

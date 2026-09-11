<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Models;

use InvalidArgumentException;

enum SupportStatus: string
{
    case Supported = 'supported';
    case Unsupported = 'unsupported';
    case Unknown = 'unknown';

    public static function fromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (!is_string($value)) {
            return self::Unknown;
        }

        return self::tryFrom($value)
            ?? throw new InvalidArgumentException("Invalid support status: {$value}");
    }

    public function isSupported(): bool
    {
        return $this === self::Supported;
    }

    public function isUnsupported(): bool
    {
        return $this === self::Unsupported;
    }
}

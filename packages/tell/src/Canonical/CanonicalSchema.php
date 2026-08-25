<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

final class CanonicalSchema
{
    public const int VERSION = 1;

    public static function assertSupported(int $schema): void
    {
        if ($schema !== self::VERSION) {
            throw new CanonicalValidationException(
                "Unsupported Tell canonical schema {$schema}; supported schema is ".self::VERSION.'.',
            );
        }
    }
}

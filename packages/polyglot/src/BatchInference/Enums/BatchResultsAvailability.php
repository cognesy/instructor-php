<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Enums;

enum BatchResultsAvailability: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Final = 'final';
    case Unavailable = 'unavailable';
    case Unsupported = 'unsupported';

    public function isAvailable(): bool
    {
        return $this === self::Partial || $this === self::Final;
    }
}

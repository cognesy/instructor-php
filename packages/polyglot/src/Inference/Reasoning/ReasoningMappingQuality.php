<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

enum ReasoningMappingQuality: string
{
    case Exact = 'exact';
    case Documented = 'documented';
    case Lossy = 'lossy';
    case Unsupported = 'unsupported';

    public function isAcceptedByDefault(): bool
    {
        return match ($this) {
            self::Exact, self::Documented => true,
            self::Lossy, self::Unsupported => false,
        };
    }
}

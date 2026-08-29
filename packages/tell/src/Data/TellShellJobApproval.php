<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

final readonly class TellShellJobApproval
{
    private function __construct(
        public bool $allowed,
        public string $reason,
    ) {}

    public static function allow(string $reason = 'approved by host policy'): self {
        return new self(true, $reason);
    }

    public static function deny(string $reason = 'denied by host policy'): self {
        return new self(false, $reason);
    }
}

<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

final readonly class TellSessionRemoval
{
    public function __construct(
        public string $sessionId,
        public bool $removed,
    ) {}
}

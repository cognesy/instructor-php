<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

final readonly class TellShellJobHealth
{
    /** @param list<string> $missing */
    public function __construct(
        public string $module,
        public string $state,
        public array $missing = [],
        public ?string $error = null,
    ) {}
}

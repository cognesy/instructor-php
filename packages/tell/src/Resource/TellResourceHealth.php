<?php

declare(strict_types=1);

namespace Cognesy\Tell\Resource;

final readonly class TellResourceHealth
{
    /** @param list<string> $missing */
    public function __construct(
        public string $module,
        public string $state,
        public array $missing = [],
        public ?string $error = null,
    ) {}
}

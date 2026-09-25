<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

final readonly class StoreContinuation
{
    /** @param array<string, scalar|null> $position */
    public function __construct(
        public string $driver,
        public array $position,
    ) {}
}

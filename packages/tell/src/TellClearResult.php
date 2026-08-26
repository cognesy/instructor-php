<?php

declare(strict_types=1);

namespace Cognesy\Tell;

/** The result of atomically clearing one Tell conversation selector. */
final readonly class TellClearResult
{
    /** @param array{name: string, type: 'main'|'branch'|'session'|'ref', source?: string} $selector */
    public function __construct(
        public array $selector,
        public ?string $previousHead,
        public ?string $head,
    ) {}

    public function changed(): bool
    {
        return $this->previousHead !== null;
    }

    public function isEmpty(): bool
    {
        return $this->head === null;
    }
}

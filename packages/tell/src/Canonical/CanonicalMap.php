<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

final readonly class CanonicalMap
{
    /** @param array<string, mixed> $values */
    public function __construct(
        private array $values,
    ) {
        CanonicalInput::map($values, 'object');
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return $this->values;
    }
}

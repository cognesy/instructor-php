<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

interface CanonicalRecord
{
    public function kind(): string;

    public function schema(): int;

    /** @return array<string, mixed> */
    public function toCanonicalArray(): array;
}

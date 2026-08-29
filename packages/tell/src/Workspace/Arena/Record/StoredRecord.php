<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena\Record;

interface StoredRecord
{
    public const int SCHEMA_VERSION = 1;

    public function kind(): string;

    /** @return array<string, mixed> */
    public function toArray(): array;
}

<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Filter;

interface MetadataFilter
{
    /** @param array<string, mixed> $metadata */
    public function matches(array $metadata): bool;
}

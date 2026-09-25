<?php declare(strict_types=1);

namespace Cognesy\Retrieval\Filter;

use Override;

final readonly class MatchAll implements MetadataFilter
{
    #[Override]
    public function matches(array $metadata): bool
    {
        return true;
    }
}

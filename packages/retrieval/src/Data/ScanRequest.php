<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

use Cognesy\Retrieval\Filter\MatchAll;
use Cognesy\Retrieval\Filter\MetadataFilter;
use InvalidArgumentException;

final readonly class ScanRequest
{
    private MetadataFilter $resolvedFilter;

    private Projection $resolvedProjection;

    public function __construct(
        public int $pageSize = 100,
        public ?StoreContinuation $continuation = null,
        ?MetadataFilter $filter = null,
        ?Projection $projection = null,
    ) {
        if ($pageSize < 1) {
            throw new InvalidArgumentException('Scan page size must be at least 1');
        }
        $this->resolvedFilter = $filter ?? new MatchAll;
        $this->resolvedProjection = $projection ?? Projection::default();
    }

    public function filter(): MetadataFilter
    {
        return $this->resolvedFilter;
    }

    public function projection(): Projection
    {
        return $this->resolvedProjection;
    }

    public function withContinuation(StoreContinuation $continuation): self
    {
        return new self($this->pageSize, $continuation, $this->resolvedFilter, $this->resolvedProjection);
    }
}

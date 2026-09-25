<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Query;

use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Data\DistanceMetric;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\StoreContinuation;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Filter\MatchAll;
use Cognesy\Retrieval\Filter\MetadataFilter;
use InvalidArgumentException;
use Override;

final readonly class VectorQuery implements StoreQuery
{
    private MetadataFilter $resolvedFilter;

    private Projection $resolvedProjection;

    public function __construct(
        public Vector $vector,
        public DistanceMetric $metric = DistanceMetric::Cosine,
        private int $maxResults = 20,
        ?MetadataFilter $filter = null,
        ?Projection $projection = null,
        public string $embeddingSpace = '',
        private ?StoreContinuation $resumeFrom = null,
    ) {
        if ($maxResults < 1) {
            throw new InvalidArgumentException('Query limit must be at least 1');
        }
        new VectorDocument('__query__', $vector);
        $this->resolvedFilter = $filter ?? new MatchAll;
        $this->resolvedProjection = $projection ?? Projection::default();
    }

    #[Override]
    public function limit(): int
    {
        return $this->maxResults;
    }

    #[Override]
    public function filter(): MetadataFilter
    {
        return $this->resolvedFilter;
    }

    #[Override]
    public function projection(): Projection
    {
        return $this->resolvedProjection;
    }

    #[Override]
    public function continuation(): ?StoreContinuation
    {
        return $this->resumeFrom;
    }

    #[Override]
    public function withContinuation(StoreContinuation $continuation): self
    {
        return new self(
            $this->vector,
            $this->metric,
            $this->maxResults,
            $this->resolvedFilter,
            $this->resolvedProjection,
            $this->embeddingSpace,
            $continuation,
        );
    }
}

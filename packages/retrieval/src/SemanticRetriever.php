<?php

declare(strict_types=1);

namespace Cognesy\Retrieval;

use Cognesy\Retrieval\Contracts\CanRetrieveText;
use Cognesy\Retrieval\Data\DistanceMetric;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Filter\MatchAll;
use Cognesy\Retrieval\Filter\MetadataFilter;
use Cognesy\Retrieval\Query\SemanticQuery;
use Override;

final readonly class SemanticRetriever implements CanRetrieveText
{
    private MetadataFilter $filter;

    private Projection $projection;

    public function __construct(
        private Retrieval $retrieval,
        private DistanceMetric $metric = DistanceMetric::Cosine,
        private string $embeddingSpace = '',
        ?MetadataFilter $filter = null,
        ?Projection $projection = null,
    ) {
        $this->filter = $filter ?? new MatchAll;
        $this->projection = $projection ?? Projection::default();
    }

    #[Override]
    public function retrieve(string $query, int $maxResults = 5): PendingRetrieval
    {
        return $this->retrieval->withQuery(new SemanticQuery(
            text: $query,
            metric: $this->metric,
            maxResults: $maxResults,
            filter: $this->filter,
            projection: $this->projection,
            embeddingSpace: $this->embeddingSpace,
        ))->pending();
    }
}

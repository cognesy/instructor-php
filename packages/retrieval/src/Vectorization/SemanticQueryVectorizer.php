<?php declare(strict_types=1);

namespace Cognesy\Retrieval\Vectorization;

use Cognesy\Retrieval\Contracts\CanPrepareStoreQuery;
use Cognesy\Retrieval\Query\SemanticQuery;
use Cognesy\Retrieval\Query\StoreQuery;
use Cognesy\Retrieval\Query\VectorQuery;
use InvalidArgumentException;
use Override;

final readonly class SemanticQueryVectorizer implements CanPrepareStoreQuery
{
    public function __construct(private Vectorizer $vectorizer) {}

    #[Override]
    public function prepare(StoreQuery $query): StoreQuery
    {
        return match (true) {
            $query instanceof VectorQuery => $query,
            $query instanceof SemanticQuery => new VectorQuery(
                vector: $this->vectorizer->vectorizeText($query->text),
                metric: $query->metric,
                maxResults: $query->limit(),
                filter: $query->filter(),
                projection: $query->projection(),
                embeddingSpace: $query->embeddingSpace,
                resumeFrom: $query->continuation(),
            ),
            default => throw new InvalidArgumentException('Query vectorizer cannot prepare '.$query::class),
        };
    }
}

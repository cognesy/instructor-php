<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\InMemory;

use Cognesy\Retrieval\Contracts\CanFetchDocuments;
use Cognesy\Retrieval\Contracts\CanScanDocuments;
use Cognesy\Retrieval\Contracts\CanStoreDocuments;
use Cognesy\Retrieval\Data\DistanceMetric;
use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Data\DocumentPage;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\ScanRequest;
use Cognesy\Retrieval\Data\SearchHit;
use Cognesy\Retrieval\Data\SearchHits;
use Cognesy\Retrieval\Data\SearchScore;
use Cognesy\Retrieval\Data\StoreCapabilities;
use Cognesy\Retrieval\Data\StoreContinuation;
use Cognesy\Retrieval\Data\StoredDocument;
use Cognesy\Retrieval\Data\StoredDocuments;
use Cognesy\Retrieval\Data\StorePage;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Data\WriteResult;
use Cognesy\Retrieval\Query\StoreQuery;
use Cognesy\Retrieval\Query\VectorQuery;
use InvalidArgumentException;
use Override;

final class InMemoryStore implements CanFetchDocuments, CanScanDocuments, CanStoreDocuments
{
    /** @var array<string, VectorDocument> */
    private array $documents = [];

    /** @var list<string> */
    private array $order = [];

    private ?int $dimension = null;

    private string $embeddingSpace = '';

    public function __construct(private readonly DistanceMetric $metric = DistanceMetric::Cosine) {}

    #[Override]
    public function upsert(VectorDocuments $documents): WriteResult
    {
        $this->validateBatch($documents);
        foreach ($documents as $document) {
            $key = self::key($document->id);
            if (! isset($this->documents[$key])) {
                $this->order[] = $key;
            }
            $this->documents[$key] = $document;
        }

        return new WriteResult(acknowledged: $documents->count());
    }

    #[Override]
    public function remove(DocumentIds $ids): WriteResult
    {
        $removed = 0;
        foreach ($ids as $id) {
            $key = self::key($id);
            if (! isset($this->documents[$key])) {
                continue;
            }
            unset($this->documents[$key]);
            $removed++;
        }
        if ($this->documents === []) {
            $this->dimension = null;
            $this->embeddingSpace = '';
        }

        return new WriteResult(acknowledged: $removed, missing: $ids->count() - $removed);
    }

    #[Override]
    public function clear(): WriteResult
    {
        $removed = count($this->documents);
        $this->documents = [];
        $this->order = [];
        $this->dimension = null;
        $this->embeddingSpace = '';

        return new WriteResult(acknowledged: $removed);
    }

    #[Override]
    public function query(StoreQuery $query): StorePage
    {
        if (! $query instanceof VectorQuery) {
            throw new InvalidArgumentException('InMemoryStore supports only '.VectorQuery::class);
        }
        $this->validateQuery($query);
        $scored = [];
        foreach ($this->documents as $document) {
            if (! $query->filter()->matches($document->metadata)) {
                continue;
            }
            $scored[] = [$document, $query->metric->compare($query->vector, $document->vector)];
        }
        usort($scored, static function (array $left, array $right) use ($query): int {
            $comparison = $left[1] <=> $right[1];

            return $query->metric->higherIsBetter() ? -$comparison : $comparison;
        });
        $hits = [];
        foreach (array_slice($scored, 0, $query->limit()) as $index => [$document, $score]) {
            $projection = $query->projection();
            $hits[] = new SearchHit(
                id: $document->id,
                rank: $index + 1,
                score: new SearchScore($score, $query->metric),
                metadata: $projection->metadata ? $document->metadata : [],
                content: $projection->content ? $document->content : null,
                vector: $projection->vector ? $document->vector : null,
                embeddingSpace: $document->embeddingSpace,
            );
        }

        return new StorePage(new SearchHits($hits));
    }

    #[Override]
    public function capabilities(): StoreCapabilities
    {
        return new StoreCapabilities([VectorQuery::class], scan: true, fetch: true, exact: true);
    }

    #[Override]
    public function scan(ScanRequest $request): DocumentPage
    {
        $offset = $this->scanOffset($request->continuation);
        $documents = [];
        $position = $offset;
        $count = count($this->order);
        while ($position < $count && count($documents) < $request->pageSize) {
            $document = $this->documents[$this->order[$position]] ?? null;
            $position++;
            if ($document === null || ! $request->filter()->matches($document->metadata)) {
                continue;
            }
            $documents[] = $this->project($document, $request->projection());
        }
        $continuation = $position < $count
            ? new StoreContinuation('memory', ['offset' => $position])
            : null;

        return new DocumentPage(new StoredDocuments($documents), $continuation);
    }

    #[Override]
    public function fetch(DocumentIds $ids, Projection $projection): StoredDocuments
    {
        $documents = [];
        foreach ($ids as $id) {
            $document = $this->documents[self::key($id)] ?? null;
            if ($document !== null) {
                $documents[] = $this->project($document, $projection);
            }
        }

        return new StoredDocuments($documents);
    }

    private function validateBatch(VectorDocuments $documents): void
    {
        $dimension = $this->dimension;
        $space = $this->embeddingSpace;
        foreach ($documents as $document) {
            $dimension ??= $document->dimension();
            if ($document->dimension() !== $dimension) {
                throw new InvalidArgumentException('Vector dimension does not match store dimension');
            }
            $space = self::resolveSpace($space, $document->embeddingSpace);
        }
        $this->dimension = $dimension;
        $this->embeddingSpace = $space;
    }

    private function validateQuery(VectorQuery $query): void
    {
        if ($this->dimension !== null && count($query->vector->values()) !== $this->dimension) {
            throw new InvalidArgumentException('Query vector dimension does not match store dimension');
        }
        self::resolveSpace($this->embeddingSpace, $query->embeddingSpace);
        if ($query->metric !== $this->metric) {
            throw new InvalidArgumentException('Query metric does not match store metric');
        }
    }

    private static function resolveSpace(string $current, string $incoming): string
    {
        if ($current !== '' && $incoming !== '' && $current !== $incoming) {
            throw new InvalidArgumentException('Embedding space does not match store embedding space');
        }

        return $current !== '' ? $current : $incoming;
    }

    private static function key(int|string $id): string
    {
        return is_int($id) ? "i:{$id}" : "s:{$id}";
    }

    private function scanOffset(?StoreContinuation $continuation): int
    {
        if ($continuation === null) {
            return 0;
        }
        if ($continuation->driver !== 'memory') {
            throw new InvalidArgumentException('Scan continuation belongs to another driver');
        }
        $offset = $continuation->position['offset'] ?? null;
        if (! is_int($offset) || $offset < 0) {
            throw new InvalidArgumentException('Invalid memory scan continuation');
        }

        return $offset;
    }

    private function project(VectorDocument $document, Projection $projection): StoredDocument
    {
        return new StoredDocument(
            id: $document->id,
            metadata: $projection->metadata ? $document->metadata : [],
            content: $projection->content ? $document->content : null,
            vector: $projection->vector ? $document->vector : null,
            embeddingSpace: $document->embeddingSpace,
        );
    }
}

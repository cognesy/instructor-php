<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Typesense;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Contracts\CanFetchDocuments;
use Cognesy\Retrieval\Contracts\CanManageStore;
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
use Cognesy\Retrieval\Drivers\Support\BoundedHttpClient;
use Cognesy\Retrieval\Drivers\Support\DocumentCodec;
use Cognesy\Retrieval\Filter\MatchAll;
use Cognesy\Retrieval\Filter\MetadataEquals;
use Cognesy\Retrieval\Filter\MetadataFilter;
use Cognesy\Retrieval\Query\StoreQuery;
use Cognesy\Retrieval\Query\VectorQuery;
use InvalidArgumentException;
use JsonException;
use Override;
use RuntimeException;

final readonly class TypesenseStore implements CanFetchDocuments, CanManageStore, CanScanDocuments, CanStoreDocuments
{
    private BoundedHttpClient $client;

    public function __construct(
        private TypesenseConfig $config,
        CanSendHttpRequests $http,
    ) {
        $this->client = new BoundedHttpClient($http, 'Typesense', $config->maxResponseBytes);
    }

    #[Override]
    public function upsert(VectorDocuments $documents): WriteResult
    {
        if ($documents->count() === 0) {
            return new WriteResult;
        }
        $lines = [];
        foreach ($documents as $document) {
            $this->validateDocument($document);
            $lines[] = self::encode($this->document($document));
        }
        $response = $this->client->send(
            'POST',
            $this->config->collectionUrl('/documents/import?action=upsert'),
            $this->headers('text/plain'),
            implode("\n", $lines),
        );
        $results = $response->jsonLines('Typesense');
        if (count($results) !== $documents->count()) {
            throw new RuntimeException('Typesense import result count does not match the submitted document count');
        }
        foreach ($results as $result) {
            if (($result['success'] ?? false) !== true) {
                throw new RuntimeException('Typesense failed to import one or more documents');
            }
        }

        return new WriteResult(acknowledged: count($results));
    }

    #[Override]
    public function remove(DocumentIds $ids): WriteResult
    {
        foreach ($ids->all() as $id) {
            $this->client->send(
                'DELETE',
                $this->config->collectionUrl('/documents/'.rawurlencode(DocumentCodec::key($id)).'?ignore_not_found=true'),
                $this->headers(),
            );
        }

        return new WriteResult(acknowledged: $ids->count());
    }

    #[Override]
    public function clear(): WriteResult
    {
        $data = $this->client->send(
            'DELETE',
            $this->config->collectionUrl('/documents?truncate=true'),
            $this->headers(),
        )->json('Typesense');
        $deleted = $data['num_deleted'] ?? null;

        return is_int($deleted) ? new WriteResult(acknowledged: $deleted) : new WriteResult(unknown: 1);
    }

    #[Override]
    public function query(StoreQuery $query): StorePage
    {
        if (! $query instanceof VectorQuery || $query->continuation() !== null) {
            throw new InvalidArgumentException('Typesense supports bounded vector queries without portable ranked continuation');
        }
        $this->validateQuery($query);
        $search = array_filter([
            'collection' => $this->config->collection,
            'q' => '*',
            'vector_query' => 'vector:(['.implode(',', $query->vector->values()).'],k:'.$query->limit().')',
            'per_page' => $query->limit(),
            'include_fields' => implode(',', $this->queryFields($query)),
            'filter_by' => $this->filter($query->filter(), $query->embeddingSpace),
        ], static fn (mixed $value): bool => $value !== null);
        $result = $this->search($search);
        $hits = $result['hits'] ?? null;
        if (! is_array($hits)) {
            throw new RuntimeException('Typesense query response is missing hits');
        }
        $mapped = [];
        foreach (array_values($hits) as $index => $hit) {
            if (! is_array($hit) || ! is_array($hit['document'] ?? null)) {
                throw new RuntimeException('Typesense query returned an invalid hit');
            }
            $stored = $this->storedDocument($hit['document'], $query->projection());
            $mapped[] = new SearchHit(
                $stored->id,
                $index + 1,
                new SearchScore($this->score($hit, $query), $query->metric, 'typesense'),
                $stored->metadata,
                $stored->content,
                $stored->vector,
                $stored->embeddingSpace,
            );
        }

        return new StorePage(new SearchHits($mapped));
    }

    #[Override]
    public function scan(ScanRequest $request): DocumentPage
    {
        if ($request->pageSize > $this->config->maxScanPageSize) {
            throw new InvalidArgumentException('Typesense scan page exceeds the configured maximum');
        }
        if ($request->continuation !== null && $request->continuation->driver !== 'typesense') {
            throw new InvalidArgumentException('Scan continuation belongs to another driver');
        }
        $offset = $request->continuation?->position['offset'] ?? 0;
        if (! is_int($offset) || $offset < 0) {
            throw new InvalidArgumentException('Typesense scan continuation has an invalid offset');
        }
        $body = array_filter([
            'collection' => $this->config->collection,
            'q' => '*',
            'per_page' => $request->pageSize,
            'offset' => $offset,
            'sort_by' => 'backend_id:asc',
            'include_fields' => implode(',', $this->fields($request->projection())),
            'filter_by' => $this->filter($request->filter()),
        ], static fn (mixed $value): bool => $value !== null);
        $data = $this->search($body);
        $hits = $data['hits'] ?? null;
        if (! is_array($hits)) {
            throw new RuntimeException('Typesense scan response is missing hits');
        }
        $documents = [];
        foreach ($hits as $hit) {
            if (is_array($hit) && is_array($hit['document'] ?? null)) {
                $documents[] = $this->storedDocument($hit['document'], $request->projection());
            }
        }
        $nextOffset = $offset + count($documents);
        $found = $data['found'] ?? null;
        $hasMore = is_int($found)
            ? $nextOffset < $found
            : count($documents) === $request->pageSize;
        $continuation = $hasMore ? new StoreContinuation('typesense', ['offset' => $nextOffset]) : null;

        return new DocumentPage(new StoredDocuments($documents), $continuation);
    }

    #[Override]
    public function fetch(DocumentIds $ids, Projection $projection): StoredDocuments
    {
        $documents = [];
        foreach (array_chunk($ids->all(), $this->config->boundedScanPageSize()) as $chunk) {
            $keys = array_map(DocumentCodec::key(...), $chunk);
            $data = $this->client->send(
                'POST',
                $this->config->endpointUrl('/multi_search'),
                $this->headers('application/json'),
                self::encode(['searches' => [[
                    'collection' => $this->config->collection,
                    'q' => '*',
                    'filter_by' => 'backend_id:=['.implode(',', $keys).']',
                    'per_page' => count($keys),
                    'include_fields' => implode(',', $this->fields($projection)),
                ]]]),
            )->json('Typesense');
            $results = $data['results'] ?? null;
            $first = is_array($results) ? ($results[0] ?? null) : null;
            $hits = is_array($first) ? ($first['hits'] ?? null) : null;
            if (! is_array($hits)) {
                throw new RuntimeException('Typesense fetch response is missing hits');
            }
            foreach ($hits as $hit) {
                if (is_array($hit) && is_array($hit['document'] ?? null)) {
                    $documents[] = $this->storedDocument($hit['document'], $projection);
                }
            }
        }

        return new StoredDocuments($documents);
    }

    #[Override]
    public function capabilities(): StoreCapabilities
    {
        return new StoreCapabilities(
            [VectorQuery::class],
            scan: true,
            fetch: true,
            approximate: true,
            ordering: 'strict',
        );
    }

    #[Override]
    public function setup(): void
    {
        $this->client->send(
            'POST',
            $this->config->endpointUrl('/collections'),
            $this->headers('application/json'),
            self::encode([
                'name' => $this->config->collection,
                'fields' => [
                    ['name' => 'backend_id', 'type' => 'string', 'sort' => true],
                    ['name' => 'original_id', 'type' => 'string', 'index' => false],
                    ['name' => 'id_type', 'type' => 'string', 'index' => false],
                    ['name' => 'vector', 'type' => 'float[]', 'num_dim' => $this->config->dimensions, 'vec_dist' => $this->vectorDistance()],
                    ['name' => 'metadata_json', 'type' => 'string', 'index' => false],
                    ['name' => 'metadata_terms', 'type' => 'string[]', 'facet' => true],
                    ['name' => 'content', 'type' => 'string', 'index' => false, 'optional' => true],
                    ['name' => 'embedding_space', 'type' => 'string', 'index' => false, 'optional' => true],
                ],
            ]),
            [201],
        );
    }

    #[Override]
    public function drop(): void
    {
        $this->client->send('DELETE', $this->config->collectionUrl(), $this->headers());
    }

    /** @return array<string, mixed> */
    private function document(VectorDocument $document): array
    {
        $identity = DocumentCodec::identity($document->id);
        $terms = DocumentCodec::metadataTokens($document->metadata);
        if ($document->embeddingSpace !== '') {
            $terms[] = DocumentCodec::metadataToken("\0embedding_space", $document->embeddingSpace);
        }

        return [
            'id' => DocumentCodec::key($document->id),
            'backend_id' => DocumentCodec::key($document->id),
            ...$identity,
            'vector' => $document->vector->values(),
            'metadata_json' => DocumentCodec::encodeMetadata($document->metadata),
            'metadata_terms' => $terms,
            'content' => $document->content,
            'embedding_space' => $document->embeddingSpace,
        ];
    }

    /** @return list<string> */
    private function fields(Projection $projection): array
    {
        return array_values(array_filter([
            'backend_id',
            'original_id',
            'id_type',
            $projection->metadata ? 'metadata_json' : null,
            $projection->content ? 'content' : null,
            $projection->vector ? 'vector' : null,
            'embedding_space',
        ]));
    }

    /** @return list<string> */
    private function queryFields(VectorQuery $query): array
    {
        $fields = $this->fields($query->projection());
        if ($query->metric === DistanceMetric::DotProduct && ! in_array('vector', $fields, true)) {
            $fields[] = 'vector';
        }

        return $fields;
    }

    /** @param array<array-key, mixed> $document */
    private function storedDocument(array $document, Projection $projection): StoredDocument
    {
        $metadata = $projection->metadata && is_string($document['metadata_json'] ?? null)
            ? DocumentCodec::decodeMetadata($document['metadata_json'])
            : [];
        $content = $projection->content && is_string($document['content'] ?? null) ? $document['content'] : null;
        $values = $document['vector'] ?? null;
        $vector = $projection->vector && is_array($values)
            ? new Vector(array_map(static fn (mixed $value): float => (float) $value, $values))
            : null;

        /** @var array<string, mixed> $document */
        return new StoredDocument(
            DocumentCodec::restoreId($document),
            $metadata,
            $content,
            $vector,
            is_string($document['embedding_space'] ?? null) ? $document['embedding_space'] : '',
        );
    }

    private function validateDocument(VectorDocument $document): void
    {
        if ($document->dimension() !== $this->config->dimensions) {
            throw new InvalidArgumentException('Typesense document vector dimension does not match the collection');
        }
    }

    private function validateQuery(VectorQuery $query): void
    {
        if (count($query->vector->values()) !== $this->config->dimensions) {
            throw new InvalidArgumentException('Typesense query vector dimension does not match the collection');
        }
        if ($query->metric !== $this->config->metric) {
            throw new InvalidArgumentException('Typesense query metric does not match the collection');
        }
    }

    private function filter(MetadataFilter $filter, string $embeddingSpace = ''): ?string
    {
        $terms = match (true) {
            $filter instanceof MatchAll => [],
            $filter instanceof MetadataEquals => [DocumentCodec::metadataToken($filter->field, $filter->value)],
            default => throw new InvalidArgumentException('Filter is not supported by Typesense'),
        };
        if ($embeddingSpace !== '') {
            $terms[] = DocumentCodec::metadataToken("\0embedding_space", $embeddingSpace);
        }
        if ($terms === []) {
            return null;
        }

        return implode(' && ', array_map(
            static fn (string $term): string => "metadata_terms:=[{$term}]",
            $terms,
        ));
    }

    /** @param array<array-key, mixed> $hit */
    private function score(array $hit, VectorQuery $query): float
    {
        if ($query->metric === DistanceMetric::DotProduct) {
            $values = is_array($hit['document'] ?? null) ? ($hit['document']['vector'] ?? null) : null;
            if (! is_array($values)) {
                throw new RuntimeException('Typesense inner-product hit is missing its vector');
            }

            return $query->vector->compareTo(
                new Vector(array_map(static fn (mixed $value): float => (float) $value, $values)),
                DistanceMetric::DotProduct->value,
            );
        }
        $distance = $hit['vector_distance'] ?? null;
        if (! is_int($distance) && ! is_float($distance)) {
            throw new RuntimeException('Typesense query hit is missing vector distance');
        }

        return match ($this->config->metric) {
            DistanceMetric::Cosine => 1.0 - (float) $distance,
            DistanceMetric::Euclidean => (float) $distance,
            DistanceMetric::DotProduct => throw new RuntimeException('Unreachable Typesense score branch'),
        };
    }

    private function vectorDistance(): string
    {
        return match ($this->config->metric) {
            DistanceMetric::Cosine => 'cosine',
            DistanceMetric::DotProduct => 'ip',
            DistanceMetric::Euclidean => throw new InvalidArgumentException('Typesense does not support Euclidean vector distance'),
        };
    }

    /**
     * @param  array<string, mixed>  $search
     * @return array<string, mixed>
     */
    private function search(array $search): array
    {
        $data = $this->client->send(
            'POST',
            $this->config->endpointUrl('/multi_search'),
            $this->headers('application/json'),
            self::encode(['searches' => [$search]]),
        )->json('Typesense');
        $results = $data['results'] ?? null;
        $first = is_array($results) ? ($results[0] ?? null) : null;
        if (! is_array($first)) {
            throw new RuntimeException('Typesense search response is missing its result');
        }

        /** @var array<string, mixed> $first */
        return $first;
    }

    /** @return array<string, string> */
    private function headers(string $contentType = 'application/json'): array
    {
        return array_filter([
            'Content-Type' => $contentType,
            'X-TYPESENSE-API-KEY' => $this->config->apiKey !== '' ? $this->config->apiKey : null,
        ], static fn (?string $value): bool => $value !== null);
    }

    /** @param array<array-key, mixed> $value */
    private static function encode(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Typesense payload must be JSON serializable', 0, $error);
        }
    }
}

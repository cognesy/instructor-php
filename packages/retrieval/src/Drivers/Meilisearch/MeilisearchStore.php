<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Meilisearch;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Contracts\CanFetchDocuments;
use Cognesy\Retrieval\Contracts\CanManageStore;
use Cognesy\Retrieval\Contracts\CanScanDocuments;
use Cognesy\Retrieval\Contracts\CanStoreDocuments;
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

final readonly class MeilisearchStore implements CanFetchDocuments, CanManageStore, CanScanDocuments, CanStoreDocuments
{
    private BoundedHttpClient $client;

    public function __construct(
        private MeilisearchConfig $config,
        CanSendHttpRequests $http,
    ) {
        $this->client = new BoundedHttpClient($http, 'Meilisearch', $config->maxResponseBytes);
    }

    #[Override]
    public function upsert(VectorDocuments $documents): WriteResult
    {
        if ($documents->count() === 0) {
            return new WriteResult;
        }
        $payload = [];
        foreach ($documents as $document) {
            $this->validateDocument($document);
            $payload[] = $this->document($document);
        }
        $this->awaitOperation('POST', '/documents', $payload);

        return new WriteResult(acknowledged: $documents->count());
    }

    #[Override]
    public function remove(DocumentIds $ids): WriteResult
    {
        if ($ids->count() === 0) {
            return new WriteResult;
        }
        $this->awaitOperation('POST', '/documents/delete-batch', array_map(DocumentCodec::key(...), $ids->all()));

        return new WriteResult(acknowledged: $ids->count());
    }

    #[Override]
    public function clear(): WriteResult
    {
        $this->awaitOperation('DELETE', '/documents');

        return new WriteResult(unknown: 1);
    }

    #[Override]
    public function query(StoreQuery $query): StorePage
    {
        if (! $query instanceof VectorQuery || $query->continuation() !== null) {
            throw new InvalidArgumentException('Meilisearch supports bounded vector queries without portable ranked continuation');
        }
        $this->validateQuery($query);
        $projection = $query->projection();
        $payload = array_filter([
            'vector' => $query->vector->values(),
            'hybrid' => ['semanticRatio' => 1.0, 'embedder' => $this->config->embedder],
            'limit' => $query->limit(),
            'filter' => $this->filter($query->filter(), $query->embeddingSpace),
            'showRankingScore' => true,
            'retrieveVectors' => $projection->vector,
            'attributesToRetrieve' => $this->fields($projection),
        ], static fn (mixed $value): bool => $value !== null);
        $data = $this->request('POST', '/search', $payload);
        $hits = $data['hits'] ?? null;
        if (! is_array($hits)) {
            throw new RuntimeException('Meilisearch query response is missing hits');
        }
        $mapped = [];
        foreach (array_values($hits) as $index => $hit) {
            if (! is_array($hit)) {
                throw new RuntimeException('Meilisearch query returned an invalid hit');
            }
            $score = $hit['_rankingScore'] ?? null;
            if (! is_int($score) && ! is_float($score)) {
                throw new RuntimeException('Meilisearch query hit is missing its ranking score');
            }
            $stored = $this->storedDocument($hit, $projection);
            $mapped[] = new SearchHit(
                $stored->id,
                $index + 1,
                new SearchScore((float) $score, $query->metric, 'meilisearch'),
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
            throw new InvalidArgumentException('Meilisearch scan page exceeds the configured maximum');
        }
        if ($request->continuation !== null && $request->continuation->driver !== 'meilisearch') {
            throw new InvalidArgumentException('Scan continuation belongs to another driver');
        }
        $offset = $request->continuation?->position['offset'] ?? 0;
        if (! is_int($offset) || $offset < 0) {
            throw new InvalidArgumentException('Meilisearch scan continuation has an invalid offset');
        }
        $projection = $request->projection();
        $data = $this->request('POST', '/documents/fetch', array_filter([
            'offset' => $offset,
            'limit' => $request->pageSize,
            'filter' => $this->filter($request->filter()),
            'fields' => $this->fields($projection),
            'retrieveVectors' => $projection->vector,
            'sort' => ['backend_id:asc'],
        ], static fn (mixed $value): bool => $value !== null));
        $results = $data['results'] ?? null;
        if (! is_array($results)) {
            throw new RuntimeException('Meilisearch document response is missing results');
        }
        $documents = [];
        foreach ($results as $result) {
            if (is_array($result)) {
                $documents[] = $this->storedDocument($result, $projection);
            }
        }
        $nextOffset = $offset + count($documents);
        $total = $data['total'] ?? null;
        $hasMore = is_int($total) ? $nextOffset < $total : count($documents) === $request->pageSize;
        $continuation = $hasMore ? new StoreContinuation('meilisearch', ['offset' => $nextOffset]) : null;

        return new DocumentPage(new StoredDocuments($documents), $continuation);
    }

    #[Override]
    public function fetch(DocumentIds $ids, Projection $projection): StoredDocuments
    {
        if ($ids->count() === 0) {
            return new StoredDocuments;
        }
        $documents = [];
        foreach (array_chunk($ids->all(), $this->config->boundedScanPageSize()) as $chunk) {
            $data = $this->request('POST', '/documents/fetch', [
                'ids' => array_map(DocumentCodec::key(...), $chunk),
                'limit' => count($chunk),
                'fields' => $this->fields($projection),
                'retrieveVectors' => $projection->vector,
            ]);
            $results = $data['results'] ?? null;
            if (! is_array($results)) {
                throw new RuntimeException('Meilisearch document response is missing results');
            }
            foreach ($results as $result) {
                if (is_array($result)) {
                    $documents[] = $this->storedDocument($result, $projection);
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
        $this->awaitOperationAt(
            'POST',
            $this->config->endpointUrl('/indexes'),
            ['uid' => $this->config->index, 'primaryKey' => 'backend_id'],
        );
        $this->awaitOperation('PATCH', '/settings', [
            'embedders' => [
                $this->config->embedder => [
                    'source' => 'userProvided',
                    'dimensions' => $this->config->dimensions,
                ],
            ],
            'filterableAttributes' => ['backend_id', 'metadata_terms'],
            'sortableAttributes' => ['backend_id'],
            'searchableAttributes' => ['content'],
        ]);
    }

    #[Override]
    public function drop(): void
    {
        $this->awaitOperationAt('DELETE', $this->config->indexUrl());
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
            'backend_id' => DocumentCodec::key($document->id),
            ...$identity,
            'metadata_json' => DocumentCodec::encodeMetadata($document->metadata),
            'metadata_terms' => $terms,
            'content' => $document->content,
            'embedding_space' => $document->embeddingSpace,
            '_vectors' => [$this->config->embedder => $document->vector->values()],
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
            'embedding_space',
        ]));
    }

    /** @param array<array-key, mixed> $document */
    private function storedDocument(array $document, Projection $projection): StoredDocument
    {
        $metadata = $projection->metadata && is_string($document['metadata_json'] ?? null)
            ? DocumentCodec::decodeMetadata($document['metadata_json'])
            : [];
        $content = $projection->content && is_string($document['content'] ?? null) ? $document['content'] : null;

        return new StoredDocument(
            DocumentCodec::restoreId($document),
            $metadata,
            $content,
            $projection->vector ? $this->vector($document) : null,
            is_string($document['embedding_space'] ?? null) ? $document['embedding_space'] : '',
        );
    }

    /** @param array<array-key, mixed> $document */
    private function vector(array $document): Vector
    {
        $vectors = $document['_vectors'] ?? null;
        $embedder = is_array($vectors) ? ($vectors[$this->config->embedder] ?? null) : null;
        $embeddings = is_array($embedder) ? ($embedder['embeddings'] ?? $embedder) : null;
        $values = is_array($embeddings) && is_array($embeddings[0] ?? null) ? $embeddings[0] : $embeddings;
        if (! is_array($values)) {
            throw new RuntimeException('Meilisearch document is missing its requested vector');
        }

        return new Vector(array_map(static fn (mixed $value): float => (float) $value, $values));
    }

    private function validateDocument(VectorDocument $document): void
    {
        if ($document->dimension() !== $this->config->dimensions) {
            throw new InvalidArgumentException('Meilisearch document vector dimension does not match the index');
        }
    }

    private function validateQuery(VectorQuery $query): void
    {
        if (count($query->vector->values()) !== $this->config->dimensions) {
            throw new InvalidArgumentException('Meilisearch query vector dimension does not match the index');
        }
        if ($query->metric !== $this->config->metric) {
            throw new InvalidArgumentException('Meilisearch query metric does not match the index');
        }
    }

    private function filter(MetadataFilter $filter, string $embeddingSpace = ''): ?string
    {
        $terms = match (true) {
            $filter instanceof MatchAll => [],
            $filter instanceof MetadataEquals => [DocumentCodec::metadataToken($filter->field, $filter->value)],
            default => throw new InvalidArgumentException('Filter is not supported by Meilisearch'),
        };
        if ($embeddingSpace !== '') {
            $terms[] = DocumentCodec::metadataToken("\0embedding_space", $embeddingSpace);
        }
        if ($terms === []) {
            return null;
        }

        return implode(' AND ', array_map(
            static fn (string $term): string => 'metadata_terms = "'.$term.'"',
            $terms,
        ));
    }

    /** @param array<array-key, mixed>|null $payload */
    private function awaitOperation(string $method, string $path, ?array $payload = null): void
    {
        $this->awaitOperationAt($method, $this->config->indexUrl($path), $payload);
    }

    /** @param array<array-key, mixed>|null $payload */
    private function awaitOperationAt(string $method, string $url, ?array $payload = null): void
    {
        $response = $this->client->send(
            $method,
            $url,
            $this->headers(),
            $payload === null ? '' : self::encode($payload),
            [202],
        )->json('Meilisearch');
        $taskUid = $response['taskUid'] ?? null;
        if (! is_int($taskUid)) {
            throw new RuntimeException('Meilisearch operation did not return a task uid');
        }
        $this->awaitTask($taskUid);
    }

    private function awaitTask(int $taskUid): void
    {
        $deadline = hrtime(true) + ($this->config->taskTimeoutMilliseconds * 1_000_000);
        while (true) {
            $task = $this->client->send(
                'GET',
                $this->config->endpointUrl('/tasks/'.$taskUid),
                $this->headers(),
            )->json('Meilisearch');
            $status = $task['status'] ?? null;
            if ($status === 'succeeded') {
                return;
            }
            if ($status === 'failed' || $status === 'canceled') {
                throw new RuntimeException("Meilisearch task {$taskUid} {$status}");
            }
            if ($status !== 'enqueued' && $status !== 'processing') {
                throw new RuntimeException("Meilisearch task {$taskUid} returned an invalid status");
            }
            if (hrtime(true) >= $deadline) {
                throw new RuntimeException("Meilisearch task {$taskUid} timed out");
            }
            if ($this->config->taskPollMilliseconds > 0) {
                usleep($this->config->taskPollMilliseconds * 1_000);
            }
        }
    }

    /**
     * @param  array<array-key, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        return $this->client->send(
            $method,
            $this->config->indexUrl($path),
            $this->headers(),
            $payload === null ? '' : self::encode($payload),
        )->json('Meilisearch');
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return array_filter([
            'Content-Type' => 'application/json',
            'Authorization' => $this->config->apiKey !== '' ? 'Bearer '.$this->config->apiKey : null,
        ], static fn (?string $value): bool => $value !== null);
    }

    /** @param array<array-key, mixed> $value */
    private static function encode(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Meilisearch payload must be JSON serializable', 0, $error);
        }
    }
}

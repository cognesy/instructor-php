<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Weaviate;

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

final readonly class WeaviateStore implements CanFetchDocuments, CanManageStore, CanScanDocuments, CanStoreDocuments
{
    private BoundedHttpClient $client;

    public function __construct(
        private WeaviateConfig $config,
        CanSendHttpRequests $http,
    ) {
        $this->client = new BoundedHttpClient($http, 'Weaviate', $config->maxResponseBytes);
    }

    #[Override]
    public function upsert(VectorDocuments $documents): WriteResult
    {
        foreach ($documents as $document) {
            $this->validateDocument($document);
            $uuid = DocumentCodec::uuid($document->id);
            $object = $this->object($document, $uuid);
            $exists = $this->client->send(
                'HEAD',
                $this->objectUrl($uuid),
                $this->headers(),
                acceptedStatuses: [204, 404],
            )->statusCode === 204;
            $this->client->send(
                $exists ? 'PUT' : 'POST',
                $exists ? $this->objectUrl($uuid) : $this->config->apiUrl('/objects'),
                $this->headers(),
                self::encode($object),
            );
        }

        return new WriteResult(acknowledged: $documents->count());
    }

    #[Override]
    public function remove(DocumentIds $ids): WriteResult
    {
        $acknowledged = 0;
        $missing = 0;
        foreach ($ids->all() as $id) {
            $response = $this->client->send(
                'DELETE',
                $this->objectUrl(DocumentCodec::uuid($id)),
                $this->headers(),
                acceptedStatuses: [204, 404],
            );
            $response->statusCode === 204 ? $acknowledged++ : $missing++;
        }

        return new WriteResult($acknowledged, $missing);
    }

    #[Override]
    public function clear(): WriteResult
    {
        $this->drop();
        $this->setup();

        return new WriteResult(unknown: 1);
    }

    #[Override]
    public function query(StoreQuery $query): StorePage
    {
        if (! $query instanceof VectorQuery || $query->continuation() !== null) {
            throw new InvalidArgumentException('Weaviate supports bounded vector queries without portable ranked continuation');
        }
        $this->validateQuery($query);
        $arguments = [
            'nearVector:{vector:'.self::literal($query->vector->values()).'}',
            'limit:'.$query->limit(),
        ];
        $where = $this->where($query->filter(), $query->embeddingSpace);
        if ($where !== null) {
            $arguments[] = 'where:'.$where;
        }
        $data = $this->graphQl(
            '{ Get { '.$this->config->collection.'('.implode(',', $arguments).') { '
            .$this->selection($query->projection(), distance: true).' } } }',
        );
        $hits = $this->graphQlObjects($data);
        $mapped = [];
        foreach (array_values($hits) as $index => $hit) {
            $additional = is_array($hit['_additional'] ?? null) ? $hit['_additional'] : [];
            $distance = $additional['distance'] ?? null;
            if (! is_int($distance) && ! is_float($distance)) {
                throw new RuntimeException('Weaviate query hit is missing vector distance');
            }
            $stored = $this->storedDocument($hit, $query->projection());
            $mapped[] = new SearchHit(
                $stored->id,
                $index + 1,
                new SearchScore($this->score((float) $distance), $query->metric, 'weaviate'),
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
            throw new InvalidArgumentException('Weaviate scan page exceeds the configured maximum');
        }
        if ($request->continuation !== null && $request->continuation->driver !== 'weaviate') {
            throw new InvalidArgumentException('Scan continuation belongs to another driver');
        }
        $after = $request->continuation?->position['after'] ?? null;
        if ($after !== null && (! is_string($after) || ! self::isUuid($after))) {
            throw new InvalidArgumentException('Weaviate scan continuation has an invalid UUID cursor');
        }
        $arguments = ['limit:'.($request->pageSize + 1)];
        if ($after !== null) {
            $arguments[] = 'after:'.self::literal($after);
        }
        $where = $this->where($request->filter());
        if ($where !== null) {
            $arguments[] = 'where:'.$where;
        }
        $data = $this->graphQl(
            '{ Get { '.$this->config->collection.'('.implode(',', $arguments).') { '
            .$this->selection($request->projection()).' } } }',
        );
        $objects = $this->graphQlObjects($data);
        $hasMore = count($objects) > $request->pageSize;
        $objects = array_slice($objects, 0, $request->pageSize);
        $documents = array_map(
            fn (array $object): StoredDocument => $this->storedDocument($object, $request->projection()),
            $objects,
        );
        $last = $objects === [] ? null : $objects[array_key_last($objects)];
        $lastAdditional = is_array($last) && is_array($last['_additional'] ?? null) ? $last['_additional'] : [];
        $lastId = $lastAdditional['id'] ?? null;
        if ($hasMore && (! is_string($lastId) || ! self::isUuid($lastId))) {
            throw new RuntimeException('Weaviate scan response is missing its UUID cursor');
        }
        $continuation = $hasMore ? new StoreContinuation('weaviate', ['after' => $lastId]) : null;

        return new DocumentPage(new StoredDocuments($documents), $continuation);
    }

    #[Override]
    public function fetch(DocumentIds $ids, Projection $projection): StoredDocuments
    {
        $documents = [];
        foreach ($ids->all() as $id) {
            $response = $this->client->send(
                'GET',
                $this->objectUrl(DocumentCodec::uuid($id)).($projection->vector ? '?include=vector' : ''),
                $this->headers(),
                acceptedStatuses: [200, 404],
            );
            if ($response->statusCode === 404) {
                continue;
            }
            $object = $response->json('Weaviate');
            $properties = $object['properties'] ?? null;
            if (! is_array($properties)) {
                throw new RuntimeException('Weaviate object response is missing properties');
            }
            $properties['_additional'] = [
                'id' => $object['id'] ?? null,
                'vector' => $object['vector'] ?? null,
            ];
            $documents[] = $this->storedDocument($properties, $projection);
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
            $this->config->apiUrl('/schema'),
            $this->headers(),
            self::encode([
                'class' => $this->config->collection,
                'vectorizer' => 'none',
                'vectorIndexConfig' => ['distance' => $this->distanceName()],
                'properties' => [
                    ['name' => 'original_id', 'dataType' => ['text'], 'tokenization' => 'field'],
                    ['name' => 'id_type', 'dataType' => ['text'], 'tokenization' => 'field'],
                    ['name' => 'metadata_json', 'dataType' => ['text'], 'tokenization' => 'field'],
                    ['name' => 'metadata_terms', 'dataType' => ['text[]'], 'tokenization' => 'field'],
                    ['name' => 'content', 'dataType' => ['text']],
                    ['name' => 'embedding_space', 'dataType' => ['text'], 'tokenization' => 'field'],
                ],
            ]),
        );
    }

    #[Override]
    public function drop(): void
    {
        $this->client->send(
            'DELETE',
            $this->config->apiUrl('/schema/'.rawurlencode($this->config->collection)),
            $this->headers(),
        );
    }

    /** @return array<string, mixed> */
    private function object(VectorDocument $document, string $uuid): array
    {
        $identity = DocumentCodec::identity($document->id);
        $terms = DocumentCodec::metadataTokens($document->metadata);
        if ($document->embeddingSpace !== '') {
            $terms[] = DocumentCodec::metadataToken("\0embedding_space", $document->embeddingSpace);
        }

        return [
            'class' => $this->config->collection,
            'id' => $uuid,
            'properties' => [
                ...$identity,
                'metadata_json' => DocumentCodec::encodeMetadata($document->metadata),
                'metadata_terms' => $terms,
                'content' => $document->content,
                'embedding_space' => $document->embeddingSpace,
            ],
            'vector' => $document->vector->values(),
        ];
    }

    private function selection(Projection $projection, bool $distance = false): string
    {
        $properties = array_values(array_filter([
            'original_id',
            'id_type',
            $projection->metadata ? 'metadata_json' : null,
            $projection->content ? 'content' : null,
            'embedding_space',
        ]));
        $additional = array_values(array_filter([
            'id',
            $distance ? 'distance' : null,
            $projection->vector ? 'vector' : null,
        ]));

        return implode(' ', $properties).' _additional { '.implode(' ', $additional).' }';
    }

    /** @param array<array-key, mixed> $document */
    private function storedDocument(array $document, Projection $projection): StoredDocument
    {
        $metadata = $projection->metadata && is_string($document['metadata_json'] ?? null)
            ? DocumentCodec::decodeMetadata($document['metadata_json'])
            : [];
        $content = $projection->content && is_string($document['content'] ?? null) ? $document['content'] : null;
        $additional = is_array($document['_additional'] ?? null) ? $document['_additional'] : [];
        $values = $additional['vector'] ?? null;
        $vector = $projection->vector && is_array($values)
            ? new Vector(array_map(static fn (mixed $value): float => (float) $value, $values))
            : null;

        return new StoredDocument(
            DocumentCodec::restoreId($document),
            $metadata,
            $content,
            $vector,
            is_string($document['embedding_space'] ?? null) ? $document['embedding_space'] : '',
        );
    }

    private function where(MetadataFilter $filter, string $embeddingSpace = ''): ?string
    {
        $terms = match (true) {
            $filter instanceof MatchAll => [],
            $filter instanceof MetadataEquals => [DocumentCodec::metadataToken($filter->field, $filter->value)],
            default => throw new InvalidArgumentException('Filter is not supported by Weaviate'),
        };
        if ($embeddingSpace !== '') {
            $terms[] = DocumentCodec::metadataToken("\0embedding_space", $embeddingSpace);
        }
        if ($terms === []) {
            return null;
        }
        $operands = array_map(
            static fn (string $term): string => '{path:["metadata_terms"],operator:ContainsAny,valueText:['.self::literal($term).']}',
            $terms,
        );

        return count($operands) === 1
            ? $operands[0]
            : '{operator:And,operands:['.implode(',', $operands).']}';
    }

    /** @return array<string, mixed> */
    private function graphQl(string $query): array
    {
        $data = $this->client->send(
            'POST',
            $this->config->apiUrl('/graphql'),
            $this->headers(),
            self::encode(['query' => $query]),
        )->json('Weaviate');
        if (is_array($data['errors'] ?? null) && $data['errors'] !== []) {
            throw new RuntimeException('Weaviate GraphQL request returned errors');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<array-key, mixed>>
     */
    private function graphQlObjects(array $data): array
    {
        $root = $data['data'] ?? null;
        $get = is_array($root) ? ($root['Get'] ?? null) : null;
        $objects = is_array($get) ? ($get[$this->config->collection] ?? null) : null;
        if (! is_array($objects)) {
            throw new RuntimeException('Weaviate GraphQL response is missing collection results');
        }
        $result = [];
        foreach ($objects as $object) {
            if (! is_array($object)) {
                throw new RuntimeException('Weaviate GraphQL returned an invalid object');
            }
            $result[] = $object;
        }

        return $result;
    }

    private function validateDocument(VectorDocument $document): void
    {
        if ($document->dimension() !== $this->config->dimensions) {
            throw new InvalidArgumentException('Weaviate document vector dimension does not match the collection');
        }
    }

    private function validateQuery(VectorQuery $query): void
    {
        if (count($query->vector->values()) !== $this->config->dimensions) {
            throw new InvalidArgumentException('Weaviate query vector dimension does not match the collection');
        }
        if ($query->metric !== $this->config->metric) {
            throw new InvalidArgumentException('Weaviate query metric does not match the collection');
        }
    }

    private function score(float $distance): float
    {
        return match ($this->config->metric) {
            DistanceMetric::Cosine => 1.0 - $distance,
            DistanceMetric::DotProduct => -$distance,
            DistanceMetric::Euclidean => sqrt(max(0.0, $distance)),
        };
    }

    private function distanceName(): string
    {
        return match ($this->config->metric) {
            DistanceMetric::Cosine => 'cosine',
            DistanceMetric::DotProduct => 'dot',
            DistanceMetric::Euclidean => 'l2-squared',
        };
    }

    private function objectUrl(string $uuid): string
    {
        return $this->config->apiUrl(
            '/objects/'.rawurlencode($this->config->collection).'/'.rawurlencode($uuid),
        );
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return array_filter([
            'Content-Type' => 'application/json',
            'Authorization' => $this->config->apiKey !== '' ? 'Bearer '.$this->config->apiKey : null,
        ], static fn (?string $value): bool => $value !== null);
    }

    private static function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value) === 1;
    }

    private static function literal(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Weaviate GraphQL value must be JSON serializable', 0, $error);
        }
    }

    /** @param array<array-key, mixed> $value */
    private static function encode(array $value): string
    {
        return self::literal($value);
    }
}

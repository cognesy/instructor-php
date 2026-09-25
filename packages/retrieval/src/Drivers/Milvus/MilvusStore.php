<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Milvus;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Contracts\CanFetchDocuments;
use Cognesy\Retrieval\Contracts\CanManageStore;
use Cognesy\Retrieval\Contracts\CanStoreDocuments;
use Cognesy\Retrieval\Data\DistanceMetric;
use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\SearchHit;
use Cognesy\Retrieval\Data\SearchHits;
use Cognesy\Retrieval\Data\SearchScore;
use Cognesy\Retrieval\Data\StoreCapabilities;
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

final readonly class MilvusStore implements CanFetchDocuments, CanManageStore, CanStoreDocuments
{
    private BoundedHttpClient $client;

    public function __construct(
        private MilvusConfig $config,
        CanSendHttpRequests $http,
    ) {
        $this->client = new BoundedHttpClient($http, 'Milvus', $config->maxResponseBytes);
    }

    #[Override]
    public function upsert(VectorDocuments $documents): WriteResult
    {
        if ($documents->count() === 0) {
            return new WriteResult;
        }
        $entities = [];
        foreach ($documents as $document) {
            $this->validateDocument($document);
            $entities[] = $this->document($document);
        }
        $response = $this->request('/v2/vectordb/entities/upsert', [
            'collectionName' => $this->config->collection,
            'data' => $entities,
        ]);
        $data = $response['data'] ?? null;
        $upserted = is_array($data) ? ($data['upsertCount'] ?? null) : null;
        if (! is_int($upserted) || $upserted !== $documents->count()) {
            throw new RuntimeException('Milvus upsert count does not match the submitted document count');
        }

        return new WriteResult(acknowledged: $upserted);
    }

    #[Override]
    public function remove(DocumentIds $ids): WriteResult
    {
        foreach (array_chunk($ids->all(), $this->config->boundedFetchIds()) as $chunk) {
            $keys = array_map(DocumentCodec::key(...), $chunk);
            $this->request('/v2/vectordb/entities/delete', [
                'collectionName' => $this->config->collection,
                'filter' => 'backend_id in '.self::literal($keys),
            ]);
        }

        return new WriteResult(acknowledged: $ids->count());
    }

    #[Override]
    public function clear(): WriteResult
    {
        $this->request('/v2/vectordb/entities/delete', [
            'collectionName' => $this->config->collection,
            'filter' => 'backend_id != ""',
        ]);

        return new WriteResult(unknown: 1);
    }

    #[Override]
    public function query(StoreQuery $query): StorePage
    {
        if (! $query instanceof VectorQuery || $query->continuation() !== null) {
            throw new InvalidArgumentException('Milvus supports bounded vector queries without portable ranked continuation');
        }
        $this->validateQuery($query);
        $response = $this->request('/v2/vectordb/entities/search', array_filter([
            'collectionName' => $this->config->collection,
            'data' => [$query->vector->values()],
            'annsField' => 'vector',
            'limit' => $query->limit(),
            'filter' => $this->filter($query->filter(), $query->embeddingSpace),
            'outputFields' => $this->fields($query->projection()),
            'searchParams' => ['metricType' => $this->metricName()],
            'consistencyLevel' => 'Strong',
        ], static fn (mixed $value): bool => $value !== null));
        $hits = $response['data'] ?? null;
        if (! is_array($hits)) {
            throw new RuntimeException('Milvus search response is missing data');
        }
        $mapped = [];
        foreach (array_values($hits) as $index => $hit) {
            if (! is_array($hit)) {
                throw new RuntimeException('Milvus search returned an invalid hit');
            }
            $distance = $hit['distance'] ?? null;
            if (! is_int($distance) && ! is_float($distance)) {
                throw new RuntimeException('Milvus search hit is missing its score');
            }
            $stored = $this->storedDocument($hit, $query->projection());
            $mapped[] = new SearchHit(
                $stored->id,
                $index + 1,
                new SearchScore($this->score((float) $distance), $query->metric, 'milvus'),
                $stored->metadata,
                $stored->content,
                $stored->vector,
                $stored->embeddingSpace,
            );
        }

        return new StorePage(new SearchHits($mapped));
    }

    #[Override]
    public function fetch(DocumentIds $ids, Projection $projection): StoredDocuments
    {
        $documents = [];
        foreach (array_chunk($ids->all(), $this->config->boundedFetchIds()) as $chunk) {
            $response = $this->request('/v2/vectordb/entities/get', [
                'collectionName' => $this->config->collection,
                'id' => array_map(DocumentCodec::key(...), $chunk),
                'outputFields' => $this->fields($projection),
            ]);
            $entities = $response['data'] ?? null;
            if (! is_array($entities)) {
                throw new RuntimeException('Milvus get response is missing data');
            }
            foreach ($entities as $entity) {
                if (is_array($entity)) {
                    $documents[] = $this->storedDocument($entity, $projection);
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
            scan: false,
            fetch: true,
            approximate: true,
            ordering: 'strict',
        );
    }

    #[Override]
    public function setup(): void
    {
        $this->request('/v2/vectordb/collections/create', [
            'collectionName' => $this->config->collection,
            'schema' => [
                'autoID' => false,
                'enableDynamicField' => false,
                'fields' => [
                    [
                        'fieldName' => 'backend_id',
                        'dataType' => 'VarChar',
                        'isPrimary' => true,
                        'elementTypeParams' => ['max_length' => 128],
                    ],
                    [
                        'fieldName' => 'original_id',
                        'dataType' => 'VarChar',
                        'elementTypeParams' => ['max_length' => 65_535],
                    ],
                    [
                        'fieldName' => 'id_type',
                        'dataType' => 'VarChar',
                        'elementTypeParams' => ['max_length' => 16],
                    ],
                    [
                        'fieldName' => 'vector',
                        'dataType' => 'FloatVector',
                        'elementTypeParams' => ['dim' => $this->config->dimensions],
                    ],
                    ['fieldName' => 'metadata', 'dataType' => 'JSON'],
                    [
                        'fieldName' => 'content',
                        'dataType' => 'VarChar',
                        'nullable' => true,
                        'elementTypeParams' => ['max_length' => $this->config->maxContentBytes],
                    ],
                    [
                        'fieldName' => 'embedding_space',
                        'dataType' => 'VarChar',
                        'elementTypeParams' => ['max_length' => 65_535],
                    ],
                ],
            ],
            'indexParams' => [[
                'metricType' => $this->metricName(),
                'fieldName' => 'vector',
                'indexName' => 'vector_index',
                'params' => ['index_type' => 'AUTOINDEX'],
            ]],
            'params' => ['consistencyLevel' => 'Strong'],
        ]);
    }

    #[Override]
    public function drop(): void
    {
        $this->request('/v2/vectordb/collections/drop', [
            'collectionName' => $this->config->collection,
        ]);
    }

    /** @return array<string, mixed> */
    private function document(VectorDocument $document): array
    {
        return [
            'backend_id' => DocumentCodec::key($document->id),
            ...DocumentCodec::identity($document->id),
            'vector' => $document->vector->values(),
            'metadata' => $document->metadata,
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
            $projection->metadata ? 'metadata' : null,
            $projection->content ? 'content' : null,
            $projection->vector ? 'vector' : null,
            'embedding_space',
        ]));
    }

    /** @param array<array-key, mixed> $document */
    private function storedDocument(array $document, Projection $projection): StoredDocument
    {
        $storedMetadata = $document['metadata'] ?? null;
        $metadata = $projection->metadata && is_array($storedMetadata) ? $storedMetadata : [];
        $content = $projection->content && is_string($document['content'] ?? null) ? $document['content'] : null;
        $values = $document['vector'] ?? null;
        $vector = $projection->vector && is_array($values)
            ? new Vector(array_map(static fn (mixed $value): float => (float) $value, $values))
            : null;

        /** @var array<string, mixed> $metadata */
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
            throw new InvalidArgumentException('Milvus document vector dimension does not match the collection');
        }
        if (strlen((string) $document->id) > 65_535 || strlen($document->embeddingSpace) > 65_535) {
            throw new InvalidArgumentException('Milvus document id or embedding space exceeds the field limit');
        }
        if ($document->content !== null && strlen($document->content) > $this->config->maxContentBytes) {
            throw new InvalidArgumentException('Milvus document content exceeds the configured field limit');
        }
        if (strlen(self::literal($document->metadata)) > 65_536) {
            throw new InvalidArgumentException('Milvus document metadata exceeds the JSON field limit');
        }
    }

    private function validateQuery(VectorQuery $query): void
    {
        if (count($query->vector->values()) !== $this->config->dimensions) {
            throw new InvalidArgumentException('Milvus query vector dimension does not match the collection');
        }
        if ($query->metric !== $this->config->metric) {
            throw new InvalidArgumentException('Milvus query metric does not match the collection');
        }
    }

    private function filter(MetadataFilter $filter, string $embeddingSpace = ''): ?string
    {
        $parts = match (true) {
            $filter instanceof MatchAll => [],
            $filter instanceof MetadataEquals => [
                'metadata['.self::literal($filter->field).'] == '.self::literal($filter->value),
            ],
            default => throw new InvalidArgumentException('Filter is not supported by Milvus'),
        };
        if ($embeddingSpace !== '') {
            $parts[] = 'embedding_space == '.self::literal($embeddingSpace);
        }

        return $parts === [] ? null : implode(' AND ', $parts);
    }

    private function metricName(): string
    {
        return match ($this->config->metric) {
            DistanceMetric::Cosine => 'COSINE',
            DistanceMetric::DotProduct => 'IP',
            DistanceMetric::Euclidean => 'L2',
        };
    }

    private function score(float $distance): float
    {
        return $this->config->metric === DistanceMetric::Euclidean
            ? sqrt(max(0.0, $distance))
            : $distance;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $path, array $payload): array
    {
        if ($this->config->database !== '') {
            $payload['dbName'] = $this->config->database;
        }
        $data = $this->client->send(
            'POST',
            $this->config->endpointUrl($path),
            $this->headers(),
            self::encode($payload),
        )->json('Milvus');
        $code = $data['code'] ?? null;
        if (! is_int($code)) {
            throw new RuntimeException('Milvus response is missing its application code');
        }
        if ($code !== 0) {
            throw new RuntimeException("Milvus request failed with application code {$code}");
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return array_filter([
            'Content-Type' => 'application/json',
            'Authorization' => $this->config->token !== '' ? 'Bearer '.$this->config->token : null,
        ], static fn (?string $value): bool => $value !== null);
    }

    private static function literal(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Milvus value must be JSON serializable', 0, $error);
        }
    }

    /** @param array<array-key, mixed> $value */
    private static function encode(array $value): string
    {
        return self::literal($value);
    }
}

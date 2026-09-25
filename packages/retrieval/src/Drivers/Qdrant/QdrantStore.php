<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Qdrant;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
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
use Cognesy\Retrieval\Filter\MatchAll;
use Cognesy\Retrieval\Filter\MetadataEquals;
use Cognesy\Retrieval\Filter\MetadataFilter;
use Cognesy\Retrieval\Query\StoreQuery;
use Cognesy\Retrieval\Query\VectorQuery;
use Cognesy\Utils\Json\Json;
use InvalidArgumentException;
use Override;
use RuntimeException;

final readonly class QdrantStore implements CanFetchDocuments, CanManageStore, CanScanDocuments, CanStoreDocuments
{
    public function __construct(
        private QdrantConfig $config,
        private CanSendHttpRequests $http,
    ) {}

    #[Override]
    public function upsert(VectorDocuments $documents): WriteResult
    {
        $points = [];
        foreach ($documents as $document) {
            $points[] = [
                'id' => self::pointId($document->id),
                'vector' => $document->vector->values(),
                'payload' => $this->payload($document),
            ];
        }
        $this->request('PUT', '/points?wait=true', ['points' => $points]);

        return new WriteResult(acknowledged: $documents->count());
    }

    #[Override]
    public function remove(DocumentIds $ids): WriteResult
    {
        $this->request('POST', '/points/delete?wait=true', [
            'points' => array_map(self::pointId(...), $ids->all()),
        ]);

        return new WriteResult(acknowledged: $ids->count());
    }

    #[Override]
    public function clear(): WriteResult
    {
        $this->request('POST', '/points/delete?wait=true', ['filter' => ['must' => []]]);

        return new WriteResult(unknown: 1);
    }

    #[Override]
    public function query(StoreQuery $query): StorePage
    {
        if (! $query instanceof VectorQuery || $query->continuation() !== null) {
            throw new InvalidArgumentException('Qdrant supports bounded vector queries without portable ranked continuation');
        }
        $projection = $query->projection();
        $data = $this->request('POST', '/points/query', [
            'query' => $query->vector->values(),
            'limit' => $query->limit(),
            'filter' => $this->filter($query->filter()),
            'with_payload' => true,
            'with_vector' => $projection->vector,
        ]);
        $points = $data['result']['points'] ?? [];
        if (! is_array($points)) {
            throw new RuntimeException('Invalid Qdrant query response');
        }
        $hits = [];
        foreach (array_values($points) as $index => $point) {
            if (! is_array($point)) {
                throw new RuntimeException('Invalid Qdrant point response');
            }
            $hits[] = $this->hit($point, $index + 1, $query);
        }

        return new StorePage(new SearchHits($hits));
    }

    #[Override]
    public function scan(ScanRequest $request): DocumentPage
    {
        $offset = $request->continuation?->position['offset'] ?? null;
        if ($request->continuation !== null && $request->continuation->driver !== 'qdrant') {
            throw new InvalidArgumentException('Scan continuation belongs to another driver');
        }
        $projection = $request->projection();
        $data = $this->request('POST', '/points/scroll', array_filter([
            'limit' => $request->pageSize,
            'offset' => $offset,
            'filter' => $this->filter($request->filter()),
            'with_payload' => true,
            'with_vector' => $projection->vector,
        ], static fn (mixed $value): bool => $value !== null));
        $result = $data['result'] ?? [];
        $points = is_array($result) && is_array($result['points'] ?? null) ? $result['points'] : [];
        $documents = [];
        foreach ($points as $point) {
            if (is_array($point)) {
                $documents[] = $this->storedDocument($point, $projection);
            }
        }
        $next = is_array($result) ? ($result['next_page_offset'] ?? null) : null;
        $continuation = is_int($next) || is_string($next)
            ? new StoreContinuation('qdrant', ['offset' => $next])
            : null;

        return new DocumentPage(new StoredDocuments($documents), $continuation);
    }

    #[Override]
    public function fetch(DocumentIds $ids, Projection $projection): StoredDocuments
    {
        $data = $this->request('POST', '/points', [
            'ids' => array_map(self::pointId(...), $ids->all()),
            'with_payload' => true,
            'with_vector' => $projection->vector,
        ]);
        $points = $data['result'] ?? [];
        $documents = [];
        if (is_array($points)) {
            foreach ($points as $point) {
                if (is_array($point)) {
                    $documents[] = $this->storedDocument($point, $projection);
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
        );
    }

    #[Override]
    public function setup(): void
    {
        $distance = match ($this->config->metric) {
            DistanceMetric::Cosine => 'Cosine',
            DistanceMetric::Euclidean => 'Euclid',
            DistanceMetric::DotProduct => 'Dot',
        };
        $this->request('PUT', '', ['vectors' => ['size' => $this->config->dimensions, 'distance' => $distance]]);
    }

    #[Override]
    public function drop(): void
    {
        $this->request('DELETE', '', []);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $body): array
    {
        $pending = $this->http->send(new HttpRequest(
            url: $this->config->collectionUrl($path),
            method: $method,
            headers: array_filter([
                'Content-Type' => 'application/json',
                'api-key' => $this->config->apiKey !== '' ? $this->config->apiKey : null,
            ], static fn (?string $value): bool => $value !== null),
            body: Json::fromArray($body)->toString(),
            options: ['stream' => true],
        ));
        $status = $pending->statusCode();
        $content = '';
        foreach ($pending->stream() as $chunk) {
            if (strlen($content) + strlen($chunk) > $this->config->maxResponseBytes) {
                throw new RuntimeException('Qdrant response exceeds configured byte limit');
            }
            $content .= $chunk;
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Qdrant request failed with HTTP {$status}");
        }

        return Json::fromString($content)->toArray();
    }

    /** @return array<string, mixed> */
    private function payload(VectorDocument $document): array
    {
        return [
            '_retrieval' => [
                'id' => (string) $document->id,
                'id_type' => is_int($document->id) ? 'int' : 'string',
            ],
            'metadata' => $document->metadata,
            'content' => $document->content,
            'embedding_space' => $document->embeddingSpace,
        ];
    }

    /** @return array<string, mixed>|null */
    private function filter(MetadataFilter $filter): ?array
    {
        return match (true) {
            $filter instanceof MatchAll => null,
            $filter instanceof MetadataEquals => [
                'must' => [['key' => 'metadata.'.$filter->field, 'match' => ['value' => $filter->value]]],
            ],
            default => throw new InvalidArgumentException('Filter is not supported by Qdrant'),
        };
    }

    /** @param array<string, mixed> $point */
    private function hit(array $point, int $rank, VectorQuery $query): SearchHit
    {
        $stored = $this->storedDocument($point, $query->projection());
        $score = $point['score'] ?? null;
        if (! is_int($score) && ! is_float($score)) {
            throw new RuntimeException('Qdrant result is missing a numeric score');
        }

        return new SearchHit(
            $stored->id,
            $rank,
            new SearchScore((float) $score, $query->metric, 'qdrant'),
            $stored->metadata,
            $stored->content,
            $stored->vector,
            $stored->embeddingSpace,
        );
    }

    /** @param array<string, mixed> $point */
    private function storedDocument(array $point, Projection $projection): StoredDocument
    {
        $id = $point['id'] ?? null;
        if (! is_int($id) && ! is_string($id)) {
            throw new RuntimeException('Qdrant point is missing a valid id');
        }
        $payload = is_array($point['payload'] ?? null) ? $point['payload'] : [];
        $identity = is_array($payload['_retrieval'] ?? null) ? $payload['_retrieval'] : [];
        $originalId = $identity['id'] ?? null;
        if (is_string($originalId)) {
            $id = ($identity['id_type'] ?? 'string') === 'int' ? (int) $originalId : $originalId;
        }
        $metadata = $projection->metadata && is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $content = $projection->content && is_string($payload['content'] ?? null) ? $payload['content'] : null;
        $space = is_string($payload['embedding_space'] ?? null) ? $payload['embedding_space'] : '';
        $values = $point['vector'] ?? null;
        $vector = $projection->vector && is_array($values)
            ? new Vector(array_map(static fn (mixed $value): float => (float) $value, $values))
            : null;

        return new StoredDocument($id, $metadata, $content, $vector, $space);
    }

    private static function pointId(int|string $id): int|string
    {
        if (is_int($id) && $id >= 0) {
            return $id;
        }
        $hash = hash('sha256', get_debug_type($id)."\0".(string) $id);

        return implode('-', [
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        ]);
    }
}

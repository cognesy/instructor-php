<?php

declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Data\DistanceMetric;
use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\Milvus\MilvusConfig;
use Cognesy\Retrieval\Drivers\Milvus\MilvusStore;
use Cognesy\Retrieval\Filter\MetadataEquals;
use Cognesy\Retrieval\Query\VectorQuery;

it('renders Milvus bulk upserts and verifies the acknowledged count', function () {
    $transport = new MilvusFakeTransport([[200, '{"code":0,"data":{"upsertCount":1}}']]);
    $store = new MilvusStore(
        new MilvusConfig('http://milvus:19530', 'documents', 2, token: 'root:Milvus'),
        HttpClient::fromDriver($transport),
    );

    $result = $store->upsert(VectorDocuments::of(
        new VectorDocument('doc-1', new Vector([1.0, 0.0]), ['tenant' => 'a'], 'evidence', 'space-v1'),
    ));
    $body = json_decode($transport->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);

    expect($result->acknowledged)->toBe(1)
        ->and($body['data'][0]['backend_id'])->toMatch('/^r_[0-9a-f]{64}$/')
        ->and($body['data'][0]['metadata'])->toBe(['tenant' => 'a'])
        ->and($transport->requests[0]->headers('Authorization'))->toBe('Bearer root:Milvus');
});

it('renders bounded Milvus vector search and safe JSON metadata filters', function () {
    $response = json_encode(['code' => 0, 'data' => [[
        'backend_id' => 'ignored',
        'original_id' => 'doc-1',
        'id_type' => 'string',
        'metadata' => ['tenant' => 'a'],
        'content' => 'evidence',
        'embedding_space' => 'space-v1',
        'distance' => 0.98,
    ]]], JSON_THROW_ON_ERROR);
    $transport = new MilvusFakeTransport([[200, $response]]);
    $store = new MilvusStore(
        new MilvusConfig('http://milvus:19530', 'documents', 2),
        HttpClient::fromDriver($transport),
    );

    $hit = $store->query(new VectorQuery(
        new Vector([1.0, 0.0]),
        filter: new MetadataEquals('tenant"] OR true OR metadata["x', 'a" OR true'),
        embeddingSpace: 'space-v1',
    ))->hits->first();
    $body = json_decode($transport->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);

    expect($body['data'])->toBe([[1.0, 0.0]])
        ->and($body['limit'])->toBe(20)
        ->and($body['searchParams']['metricType'])->toBe('COSINE')
        ->and($body['filter'])->toContain('metadata["tenant\\"] OR true OR metadata[\\"x"]')
        ->and($body['filter'])->toContain('== "a\\" OR true"')
        ->and($hit?->score->value)->toBe(0.98)
        ->and($hit?->vector)->toBeNull();
});

it('creates an explicit Milvus schema and matching vector index', function (
    DistanceMetric $metric,
    string $metricName,
) {
    $transport = new MilvusFakeTransport([[200, '{"code":0,"data":{}}']]);
    $store = new MilvusStore(
        new MilvusConfig('http://milvus:19530', 'documents', 4, $metric),
        HttpClient::fromDriver($transport),
    );

    $store->setup();
    $body = json_decode($transport->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);

    expect($body['schema']['enableDynamicField'])->toBeFalse()
        ->and($body['schema']['fields'][0]['isPrimary'])->toBeTrue()
        ->and($body['schema']['fields'][3]['elementTypeParams']['dim'])->toBe(4)
        ->and($body['indexParams'][0]['params']['index_type'])->toBe('AUTOINDEX')
        ->and($body['indexParams'][0]['metricType'])->toBe($metricName);
})->with([
    'cosine' => [DistanceMetric::Cosine, 'COSINE'],
    'dot product' => [DistanceMetric::DotProduct, 'IP'],
    'Euclidean' => [DistanceMetric::Euclidean, 'L2'],
]);

it('normalizes Milvus squared L2 to Euclidean distance', function () {
    $response = json_encode(['code' => 0, 'data' => [[
        'original_id' => 'doc',
        'id_type' => 'string',
        'metadata' => [],
        'embedding_space' => '',
        'distance' => 25.0,
    ]]], JSON_THROW_ON_ERROR);
    $store = new MilvusStore(
        new MilvusConfig('http://milvus:19530', 'documents', 2, DistanceMetric::Euclidean),
        HttpClient::fromDriver(new MilvusFakeTransport([[200, $response]])),
    );

    $score = $store->query(new VectorQuery(
        new Vector([0.0, 0.0]),
        DistanceMetric::Euclidean,
    ))->hits->first()?->score;

    expect($score?->value)->toBe(5.0)
        ->and($score?->higherIsBetter())->toBeFalse();
});

it('checks Milvus application codes and reports scan capability truthfully', function () {
    $store = new MilvusStore(
        new MilvusConfig('http://milvus:19530', 'documents', 2),
        HttpClient::fromDriver(new MilvusFakeTransport([[200, '{"code":1800,"message":"secret detail"}']])),
    );

    expect(fn () => $store->clear())
        ->toThrow(RuntimeException::class, 'application code 1800')
        ->and($store->capabilities()->scan)->toBeFalse()
        ->and($store->capabilities()->fetch)->toBeTrue()
        ->and($store->capabilities()->rankedContinuation)->toBeFalse();
});

it('validates Milvus dimensions identifiers and field limits before requests', function () {
    expect(fn () => new MilvusConfig('http://milvus:19530', 'documents', 1))
        ->toThrow(InvalidArgumentException::class, 'dimensions')
        ->and(fn () => new MilvusConfig('http://milvus:19530', 'unsafe-name', 2))
        ->toThrow(InvalidArgumentException::class, 'identifiers');

    $store = new MilvusStore(
        new MilvusConfig('http://milvus:19530', 'documents', 2, maxContentBytes: 3),
        HttpClient::fromDriver(new MilvusFakeTransport([])),
    );

    expect(fn () => $store->upsert(VectorDocuments::of(
        new VectorDocument('doc', new Vector([1.0, 0.0]), content: 'long'),
    )))->toThrow(InvalidArgumentException::class, 'content exceeds');
});

it('hydrates vectors only when explicitly projected', function () {
    $response = json_encode(['code' => 0, 'data' => [[
        'original_id' => 'doc-1',
        'id_type' => 'string',
        'metadata' => [],
        'embedding_space' => '',
        'vector' => [1.0, 0.0],
    ]]], JSON_THROW_ON_ERROR);
    $store = new MilvusStore(
        new MilvusConfig('http://milvus:19530', 'documents', 2),
        HttpClient::fromDriver(new MilvusFakeTransport([[200, $response]])),
    );

    $documents = $store->fetch(
        DocumentIds::of('doc-1'),
        new Projection(vector: true),
    )->all();

    expect($documents[0]->vector?->values())->toBe([1.0, 0.0]);
});

final class MilvusFakeTransport implements CanHandleHttpRequest
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @param list<array{0: int, 1: string}> $responses */
    public function __construct(private array $responses) {}

    public function handle(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        [$status, $body] = array_shift($this->responses) ?? [200, '{}'];

        return HttpResponse::streamingFromIterable($status, [], str_split($body, 11));
    }
}

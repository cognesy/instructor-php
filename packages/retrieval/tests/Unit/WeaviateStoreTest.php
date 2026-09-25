<?php

declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Data\DistanceMetric;
use Cognesy\Retrieval\Data\ScanRequest;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\Weaviate\WeaviateConfig;
use Cognesy\Retrieval\Drivers\Weaviate\WeaviateStore;
use Cognesy\Retrieval\Filter\MetadataEquals;
use Cognesy\Retrieval\Query\VectorQuery;

it('creates missing Weaviate objects and replaces existing objects with PUT', function () {
    $transport = new WeaviateFakeTransport([
        [404, ''],
        [200, '{}'],
        [204, ''],
        [200, '{}'],
    ]);
    $store = new WeaviateStore(
        new WeaviateConfig('http://weaviate:8080', 'Documents', 2),
        HttpClient::fromDriver($transport),
    );
    $documents = VectorDocuments::of(new VectorDocument('doc-1', new Vector([1.0, 0.0])));

    $store->upsert($documents);
    $store->upsert($documents);
    $created = json_decode($transport->requests[1]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);

    expect(array_map(static fn (HttpRequest $request): string => $request->method(), $transport->requests))
        ->toBe(['HEAD', 'POST', 'HEAD', 'PUT'])
        ->and($created['id'])->toMatch('/^[0-9a-f-]{36}$/')
        ->and($created['vector'])->toBe([1.0, 0.0]);
});

it('renders escaped GraphQL vector queries and maps cosine scores', function () {
    $response = json_encode(['data' => ['Get' => ['Documents' => [[
        'original_id' => 'doc-1',
        'id_type' => 'string',
        'metadata_json' => '{"tenant":"a"}',
        'content' => 'evidence',
        'embedding_space' => 'space-v1',
        '_additional' => ['id' => '11111111-1111-4111-8111-111111111111', 'distance' => 0.05],
    ]]]]], JSON_THROW_ON_ERROR);
    $transport = new WeaviateFakeTransport([[200, $response]]);
    $store = new WeaviateStore(
        new WeaviateConfig('http://weaviate:8080', 'Documents', 2, apiKey: 'secret'),
        HttpClient::fromDriver($transport),
    );

    $hit = $store->query(new VectorQuery(
        new Vector([1.0, 0.0]),
        filter: new MetadataEquals('tenant', 'a"} malicious'),
        embeddingSpace: 'space-v1',
    ))->hits->first();
    $body = json_decode($transport->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);

    expect($body['query'])->toContain('nearVector:{vector:[1.0,0.0]}')
        ->and($body['query'])->not->toContain('malicious')
        ->and($body['query'])->toContain('operator:And')
        ->and($hit?->score->value)->toBe(0.95)
        ->and($hit?->vector)->toBeNull()
        ->and($transport->requests[0]->headers('Authorization'))->toBe('Bearer secret');
});

it('uses UUID cursor overfetch for bounded Weaviate scans', function () {
    $response = json_encode(['data' => ['Get' => ['Documents' => [
        [
            'original_id' => 'first',
            'id_type' => 'string',
            'metadata_json' => '{}',
            'embedding_space' => '',
            '_additional' => ['id' => '11111111-1111-4111-8111-111111111111'],
        ],
        [
            'original_id' => 'second',
            'id_type' => 'string',
            'metadata_json' => '{}',
            'embedding_space' => '',
            '_additional' => ['id' => '22222222-2222-4222-8222-222222222222'],
        ],
    ]]]], JSON_THROW_ON_ERROR);
    $transport = new WeaviateFakeTransport([[200, $response]]);
    $store = new WeaviateStore(
        new WeaviateConfig('http://weaviate:8080', 'Documents', 2),
        HttpClient::fromDriver($transport),
    );

    $page = $store->scan(new ScanRequest(1));
    $body = json_decode($transport->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);

    expect($body['query'])->toContain('limit:2')
        ->and($page->documents->count())->toBe(1)
        ->and($page->continuation?->position['after'])->toBe('11111111-1111-4111-8111-111111111111');
});

it('normalizes Weaviate dot and l2-squared distances to package score semantics', function (
    DistanceMetric $metric,
    float $distance,
    float $expected,
) {
    $response = json_encode(['data' => ['Get' => ['Documents' => [[
        'original_id' => 'doc',
        'id_type' => 'string',
        'metadata_json' => '{}',
        'embedding_space' => '',
        '_additional' => ['id' => '11111111-1111-4111-8111-111111111111', 'distance' => $distance],
    ]]]]], JSON_THROW_ON_ERROR);
    $store = new WeaviateStore(
        new WeaviateConfig('http://weaviate:8080', 'Documents', 2, $metric),
        HttpClient::fromDriver(new WeaviateFakeTransport([[200, $response]])),
    );

    $score = $store->query(new VectorQuery(new Vector([1.0, 0.0]), $metric))->hits->first()?->score;

    expect($score?->value)->toBe($expected)
        ->and($score?->higherIsBetter())->toBe($metric !== DistanceMetric::Euclidean);
})->with([
    'dot product' => [DistanceMetric::DotProduct, -2.0, 2.0],
    'squared Euclidean' => [DistanceMetric::Euclidean, 25.0, 5.0],
]);

it('rejects unsafe collection names, mismatched dimensions, and GraphQL errors', function () {
    expect(fn () => new WeaviateConfig('http://weaviate:8080', 'unsafe-name', 2))
        ->toThrow(InvalidArgumentException::class, 'GraphQL name');

    $store = new WeaviateStore(
        new WeaviateConfig('http://weaviate:8080', 'Documents', 2),
        HttpClient::fromDriver(new WeaviateFakeTransport([[200, '{"errors":[{"message":"sensitive"}]}']])),
    );

    expect(fn () => $store->query(new VectorQuery(new Vector([1.0]))))
        ->toThrow(InvalidArgumentException::class, 'dimension')
        ->and(fn () => $store->query(new VectorQuery(new Vector([1.0, 0.0]))))
        ->toThrow(RuntimeException::class, 'GraphQL request returned errors');
});

final class WeaviateFakeTransport implements CanHandleHttpRequest
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

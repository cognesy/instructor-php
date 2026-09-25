<?php

declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Data\DistanceMetric;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\StoreContinuation;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\Typesense\TypesenseConfig;
use Cognesy\Retrieval\Drivers\Typesense\TypesenseStore;
use Cognesy\Retrieval\Filter\MetadataEquals;
use Cognesy\Retrieval\Query\VectorQuery;

it('renders Typesense bulk upsert and bounded vector search requests', function () {
    $queryResponse = json_encode(['results' => [[
        'hits' => [[
            'document' => [
                'original_id' => 'doc-1',
                'id_type' => 'string',
                'metadata_json' => '{"tenant":"a"}',
                'content' => 'evidence',
                'embedding_space' => 'space-v1',
            ],
            'vector_distance' => 0.05,
        ]],
    ]]], JSON_THROW_ON_ERROR);
    $transport = new TypesenseFakeTransport([
        [200, "{\"success\":true}\n"],
        [200, $queryResponse],
    ]);
    $store = new TypesenseStore(
        new TypesenseConfig('http://typesense:8108', 'docs', 2, apiKey: 'secret'),
        HttpClient::fromDriver($transport),
    );
    $store->upsert(VectorDocuments::of(
        new VectorDocument('doc-1', new Vector([1.0, 0.0]), ['tenant' => 'a'], 'evidence', 'space-v1'),
    ));
    $page = $store->query(new VectorQuery(
        new Vector([1.0, 0.0]),
        filter: new MetadataEquals('tenant', 'a'),
        embeddingSpace: 'space-v1',
    ));

    $upsert = json_decode($transport->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
    $query = json_decode($transport->requests[1]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
    expect($upsert['id'])->toMatch('/^r_[0-9a-f]{64}$/')
        ->and($upsert['vector'])->toBe([1.0, 0.0])
        ->and($query['searches'][0]['per_page'])->toBe(20)
        ->and($query['searches'][0]['include_fields'])->not->toContain('vector')
        ->and($query['searches'][0]['filter_by'])->toContain('metadata_terms')
        ->and($page->hits->first()?->id)->toBe('doc-1')
        ->and($page->hits->first()?->score->value)->toBe(0.95)
        ->and($transport->requests[0]->headers('X-TYPESENSE-API-KEY'))->toBe('secret');
});

it('rejects unsupported Typesense metrics dimensions and partial import failures', function () {
    expect(fn () => new TypesenseConfig('http://typesense:8108', 'docs', 2, DistanceMetric::Euclidean))
        ->toThrow(InvalidArgumentException::class, 'Euclidean');

    $transport = new TypesenseFakeTransport([[200, "{\"success\":false}\n"]]);
    $store = new TypesenseStore(
        new TypesenseConfig('http://typesense:8108', 'docs', 2),
        HttpClient::fromDriver($transport),
    );
    expect(fn () => $store->upsert(VectorDocuments::of(
        new VectorDocument('doc', new Vector([1.0, 0.0])),
    )))->toThrow(RuntimeException::class, 'failed to import')
        ->and(fn () => $store->query(new VectorQuery(new Vector([1.0]))))
        ->toThrow(InvalidArgumentException::class, 'dimension');
});

it('computes inner-product scores from the returned vector when Typesense omits a negative distance', function () {
    $queryResponse = json_encode(['results' => [[
        'hits' => [[
            'document' => [
                'original_id' => 'best',
                'id_type' => 'string',
                'metadata_json' => '{}',
                'embedding_space' => '',
                'vector' => [2.0, 0.0],
            ],
        ]],
    ]]], JSON_THROW_ON_ERROR);
    $transport = new TypesenseFakeTransport([[200, $queryResponse]]);
    $store = new TypesenseStore(
        new TypesenseConfig('http://typesense:8108', 'docs', 2, DistanceMetric::DotProduct),
        HttpClient::fromDriver($transport),
    );

    $hit = $store->query(new VectorQuery(
        new Vector([1.0, 0.0]),
        DistanceMetric::DotProduct,
        maxResults: 1,
    ))->hits->first();
    $query = json_decode($transport->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);

    expect($query['searches'][0]['include_fields'])->toContain('vector')
        ->and($hit?->score->value)->toBe(2.0)
        ->and($hit?->vector)->toBeNull();
});

it('advertises Typesense fetch and scan without ranked continuation', function () {
    $store = new TypesenseStore(
        new TypesenseConfig('http://typesense:8108', 'docs', 2),
        HttpClient::fromDriver(new TypesenseFakeTransport([])),
    );

    expect($store->capabilities()->fetch)->toBeTrue()
        ->and($store->capabilities()->scan)->toBeTrue()
        ->and($store->capabilities()->rankedContinuation)->toBeFalse()
        ->and($store->capabilities()->approximate)->toBeTrue()
        ->and(fn () => $store->query(new VectorQuery(
            new Vector([1.0, 0.0]),
            projection: new Projection(vector: true),
            resumeFrom: new StoreContinuation('typesense', ['offset' => 1]),
        )))->toThrow(InvalidArgumentException::class, 'ranked continuation');
});

final class TypesenseFakeTransport implements CanHandleHttpRequest
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

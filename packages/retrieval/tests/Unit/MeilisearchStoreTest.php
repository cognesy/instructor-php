<?php

declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Data\DistanceMetric;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\Meilisearch\MeilisearchConfig;
use Cognesy\Retrieval\Drivers\Meilisearch\MeilisearchStore;
use Cognesy\Retrieval\Filter\MetadataEquals;
use Cognesy\Retrieval\Query\VectorQuery;

it('awaits Meilisearch writes before acknowledging them', function () {
    $transport = new MeilisearchFakeTransport([
        [202, '{"taskUid":7,"status":"enqueued"}'],
        [200, '{"uid":7,"status":"processing"}'],
        [200, '{"uid":7,"status":"succeeded"}'],
    ]);
    $store = new MeilisearchStore(
        new MeilisearchConfig('http://meilisearch:7700', 'docs', 2, taskPollMilliseconds: 0),
        HttpClient::fromDriver($transport),
    );

    $result = $store->upsert(VectorDocuments::of(
        new VectorDocument('doc-1', new Vector([1.0, 0.0]), ['tenant' => 'a'], 'evidence', 'space-v1'),
    ));
    $document = json_decode($transport->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR)[0];

    expect($result->acknowledged)->toBe(1)
        ->and($document['backend_id'])->toMatch('/^r_[0-9a-f]{64}$/')
        ->and($document['_vectors']['instructor'])->toBe([1.0, 0.0])
        ->and($transport->requests)->toHaveCount(3);
});

it('renders a pure vector query and maps ranking score without returning vectors by default', function () {
    $response = json_encode(['hits' => [[
        'original_id' => 'doc-1',
        'id_type' => 'string',
        'metadata_json' => '{"tenant":"a"}',
        'content' => 'evidence',
        'embedding_space' => 'space-v1',
        '_rankingScore' => 0.97,
    ]]], JSON_THROW_ON_ERROR);
    $transport = new MeilisearchFakeTransport([[200, $response]]);
    $store = new MeilisearchStore(
        new MeilisearchConfig('http://meilisearch:7700', 'docs', 2, apiKey: 'secret'),
        HttpClient::fromDriver($transport),
    );

    $hit = $store->query(new VectorQuery(
        new Vector([1.0, 0.0]),
        filter: new MetadataEquals('tenant', 'a'),
        embeddingSpace: 'space-v1',
    ))->hits->first();
    $query = json_decode($transport->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);

    expect($query['hybrid'])->toBe(['semanticRatio' => 1.0, 'embedder' => 'instructor'])
        ->and($query['showRankingScore'])->toBeTrue()
        ->and($query['retrieveVectors'])->toBeFalse()
        ->and($query['filter'])->toContain('metadata_terms')
        ->and($hit?->score->value)->toBe(0.97)
        ->and($hit?->vector)->toBeNull()
        ->and($transport->requests[0]->headers('Authorization'))->toBe('Bearer secret');
});

it('hydrates Meilisearch vectors only when projected', function () {
    $response = json_encode(['hits' => [[
        'original_id' => 'doc-1',
        'id_type' => 'string',
        'metadata_json' => '{}',
        'embedding_space' => '',
        '_vectors' => ['instructor' => ['embeddings' => [[1.0, 0.0]], 'regenerate' => false]],
        '_rankingScore' => 1.0,
    ]]], JSON_THROW_ON_ERROR);
    $store = new MeilisearchStore(
        new MeilisearchConfig('http://meilisearch:7700', 'docs', 2),
        HttpClient::fromDriver(new MeilisearchFakeTransport([[200, $response]])),
    );

    $hit = $store->query(new VectorQuery(
        new Vector([1.0, 0.0]),
        projection: new Projection(vector: true),
    ))->hits->first();

    expect($hit?->vector?->values())->toBe([1.0, 0.0]);
});

it('surfaces failed tasks and rejects unsupported metrics and dimensions', function () {
    expect(fn () => new MeilisearchConfig('http://meilisearch:7700', 'docs', 2, DistanceMetric::DotProduct))
        ->toThrow(InvalidArgumentException::class, 'cosine');

    $transport = new MeilisearchFakeTransport([
        [202, '{"taskUid":9,"status":"enqueued"}'],
        [200, '{"uid":9,"status":"failed","error":{"message":"sensitive"}}'],
    ]);
    $store = new MeilisearchStore(
        new MeilisearchConfig('http://meilisearch:7700', 'docs', 2),
        HttpClient::fromDriver($transport),
    );

    expect(fn () => $store->upsert(VectorDocuments::of(
        new VectorDocument('doc', new Vector([1.0, 0.0])),
    )))->toThrow(RuntimeException::class, 'task 9 failed')
        ->and(fn () => $store->query(new VectorQuery(new Vector([1.0]))))
        ->toThrow(InvalidArgumentException::class, 'dimension');
});

it('bounds Meilisearch task polling', function () {
    $transport = new MeilisearchFakeTransport([
        [202, '{"taskUid":11,"status":"enqueued"}'],
        [200, '{"uid":11,"status":"enqueued"}'],
        [200, '{"uid":11,"status":"processing"}'],
    ]);
    $store = new MeilisearchStore(
        new MeilisearchConfig(
            'http://meilisearch:7700',
            'docs',
            2,
            taskTimeoutMilliseconds: 1,
            taskPollMilliseconds: 1,
        ),
        HttpClient::fromDriver($transport),
    );

    expect(fn () => $store->upsert(VectorDocuments::of(
        new VectorDocument('doc', new Vector([1.0, 0.0])),
    )))->toThrow(RuntimeException::class, 'task 11 timed out');
});

final class MeilisearchFakeTransport implements CanHandleHttpRequest
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

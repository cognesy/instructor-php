<?php

declare(strict_types=1);

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Data\DistanceMetric;
use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\ScanRequest;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\Weaviate\WeaviateConfig;
use Cognesy\Retrieval\Drivers\Weaviate\WeaviateStore;
use Cognesy\Retrieval\Filter\MetadataEquals;
use Cognesy\Retrieval\Query\VectorQuery;

it('roundtrips generic records through a live Weaviate collection', function () {
    $store = new WeaviateStore(
        new WeaviateConfig(
            retrievalWeaviateEndpoint(),
            'RetrievalTest'.bin2hex(random_bytes(6)),
            2,
            apiKey: retrievalWeaviateApiKey(),
        ),
        HttpClient::default(),
    );
    $store->setup();
    try {
        $store->upsert(VectorDocuments::of(
            new VectorDocument('arbitrary-id', new Vector([1.0, 0.0]), ['tenant' => 'a'], 'old', 'space-v1'),
            new VectorDocument(-7, new Vector([0.0, 1.0]), ['tenant' => 'b'], 'far', 'space-v1'),
        ));
        $store->upsert(VectorDocuments::of(
            new VectorDocument('arbitrary-id', new Vector([1.0, 0.0]), ['tenant' => 'a'], 'near', 'space-v1'),
        ));

        $page = $store->query(new VectorQuery(
            new Vector([1.0, 0.0]),
            filter: new MetadataEquals('tenant', 'a'),
            projection: new Projection(vector: true),
            embeddingSpace: 'space-v1',
        ));
        $fetched = $store->fetch(DocumentIds::of('arbitrary-id', -7), Projection::default());
        $scan = $store->scan(new ScanRequest(1));

        expect($page->hits->first()?->id)->toBe('arbitrary-id')
            ->and($page->hits->first()?->content)->toBe('near')
            ->and($page->hits->first()?->vector?->values())->toBe([1.0, 0.0])
            ->and(array_map(static fn ($document) => $document->id, $fetched->all()))
            ->toContain('arbitrary-id', -7)
            ->and($scan->documents->count())->toBe(1)
            ->and($scan->continuation)->not->toBeNull();

        $store->remove(DocumentIds::of('arbitrary-id'));
        expect($store->fetch(DocumentIds::of('arbitrary-id'), Projection::default())->count())->toBe(0);
        $cleared = $store->clear();
        expect($cleared->unknown)->toBe(1);
    } finally {
        $store->drop();
    }
})->skip(retrievalWeaviateEndpoint() === '', 'RETRIEVAL_WEAVIATE_URL is not configured');

it('normalizes live Weaviate metric distances', function (
    DistanceMetric $metric,
    Vector $storedVector,
    Vector $queryVector,
    float $expected,
) {
    $store = new WeaviateStore(
        new WeaviateConfig(
            retrievalWeaviateEndpoint(),
            'RetrievalMetric'.bin2hex(random_bytes(6)),
            2,
            $metric,
            retrievalWeaviateApiKey(),
        ),
        HttpClient::default(),
    );
    $store->setup();
    try {
        $store->upsert(VectorDocuments::of(new VectorDocument('doc', $storedVector)));
        $score = $store->query(new VectorQuery($queryVector, $metric, maxResults: 1))->hits->first()?->score;

        expect($score?->value)->toEqualWithDelta($expected, 0.000_01)
            ->and($score?->higherIsBetter())->toBe($metric !== DistanceMetric::Euclidean);
    } finally {
        $store->drop();
    }
})->with([
    'dot product' => [DistanceMetric::DotProduct, new Vector([2.0, 0.0]), new Vector([1.0, 0.0]), 2.0],
    'Euclidean' => [DistanceMetric::Euclidean, new Vector([3.0, 4.0]), new Vector([0.0, 0.0]), 5.0],
])->skip(retrievalWeaviateEndpoint() === '', 'RETRIEVAL_WEAVIATE_URL is not configured');

function retrievalWeaviateEndpoint(): string
{
    $endpoint = getenv('RETRIEVAL_WEAVIATE_URL');

    return is_string($endpoint) ? $endpoint : '';
}

function retrievalWeaviateApiKey(): string
{
    $apiKey = getenv('RETRIEVAL_WEAVIATE_API_KEY');

    return is_string($apiKey) ? $apiKey : '';
}

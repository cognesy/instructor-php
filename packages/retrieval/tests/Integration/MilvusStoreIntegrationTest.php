<?php

declare(strict_types=1);

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

it('roundtrips generic records through a live Milvus collection', function () {
    $store = new MilvusStore(
        new MilvusConfig(
            retrievalMilvusEndpoint(),
            'retrieval_test_'.bin2hex(random_bytes(6)),
            2,
            token: retrievalMilvusToken(),
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

        expect($page->hits->first()?->id)->toBe('arbitrary-id')
            ->and($page->hits->first()?->content)->toBe('near')
            ->and($page->hits->first()?->vector?->values())->toBe([1.0, 0.0])
            ->and(array_map(static fn ($document) => $document->id, $fetched->all()))
            ->toContain('arbitrary-id', -7)
            ->and($store->capabilities()->scan)->toBeFalse();

        $store->remove(DocumentIds::of('arbitrary-id'));
        expect($store->fetch(DocumentIds::of('arbitrary-id'), Projection::default())->count())->toBe(0);
        $cleared = $store->clear();
        expect($cleared->unknown)->toBe(1);
    } finally {
        $store->drop();
    }
})->skip(retrievalMilvusEndpoint() === '', 'RETRIEVAL_MILVUS_URL is not configured');

it('returns live Milvus scores with package metric direction', function (
    DistanceMetric $metric,
    Vector $storedVector,
    Vector $queryVector,
    float $expected,
) {
    $store = new MilvusStore(
        new MilvusConfig(
            retrievalMilvusEndpoint(),
            'retrieval_metric_'.bin2hex(random_bytes(6)),
            2,
            $metric,
            retrievalMilvusToken(),
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
    'cosine' => [DistanceMetric::Cosine, new Vector([1.0, 0.0]), new Vector([1.0, 0.0]), 1.0],
    'dot product' => [DistanceMetric::DotProduct, new Vector([2.0, 0.0]), new Vector([1.0, 0.0]), 2.0],
    'Euclidean' => [DistanceMetric::Euclidean, new Vector([3.0, 4.0]), new Vector([0.0, 0.0]), 5.0],
])->skip(retrievalMilvusEndpoint() === '', 'RETRIEVAL_MILVUS_URL is not configured');

function retrievalMilvusEndpoint(): string
{
    $endpoint = getenv('RETRIEVAL_MILVUS_URL');

    return is_string($endpoint) ? $endpoint : '';
}

function retrievalMilvusToken(): string
{
    $token = getenv('RETRIEVAL_MILVUS_TOKEN');

    return is_string($token) ? $token : '';
}

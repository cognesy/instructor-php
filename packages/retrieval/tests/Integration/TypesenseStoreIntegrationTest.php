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
use Cognesy\Retrieval\Drivers\Typesense\TypesenseConfig;
use Cognesy\Retrieval\Drivers\Typesense\TypesenseStore;
use Cognesy\Retrieval\Filter\MetadataEquals;
use Cognesy\Retrieval\Query\VectorQuery;

it('roundtrips generic records through a live Typesense collection', function () {
    $endpoint = retrievalTypesenseEndpoint();
    $collection = 'retrieval_test_'.bin2hex(random_bytes(6));
    $store = new TypesenseStore(
        new TypesenseConfig($endpoint, $collection, 2, apiKey: retrievalTypesenseApiKey()),
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
            embeddingSpace: 'space-v1',
        ));
        $fetched = $store->fetch(DocumentIds::of('arbitrary-id', -7), Projection::default());
        $scan = $store->scan(new ScanRequest(1));

        expect($page->hits->first()?->id)->toBe('arbitrary-id')
            ->and($page->hits->first()?->content)->toBe('near')
            ->and($page->hits->first()?->vector)->toBeNull()
            ->and(array_map(static fn ($document) => $document->id, $fetched->all()))
            ->toContain('arbitrary-id', -7)
            ->and($scan->documents->count())->toBe(1)
            ->and($scan->continuation)->not->toBeNull();

        $store->remove(DocumentIds::of('arbitrary-id'));
        expect($store->fetch(DocumentIds::of('arbitrary-id'), Projection::default())->count())->toBe(0);
        $cleared = $store->clear();
        expect($cleared->acknowledged)->toBeGreaterThanOrEqual(1);
    } finally {
        $store->drop();
    }
})->skip(retrievalTypesenseEndpoint() === '', 'RETRIEVAL_TYPESENSE_URL is not configured');

it('normalizes live Typesense inner-product distance as a higher-is-better score', function () {
    $store = new TypesenseStore(
        new TypesenseConfig(
            retrievalTypesenseEndpoint(),
            'retrieval_dot_'.bin2hex(random_bytes(6)),
            2,
            DistanceMetric::DotProduct,
            retrievalTypesenseApiKey(),
        ),
        HttpClient::default(),
    );
    $store->setup();
    try {
        $store->upsert(VectorDocuments::of(
            new VectorDocument('best', new Vector([2.0, 0.0])),
            new VectorDocument('other', new Vector([0.0, 1.0])),
        ));

        $hit = $store->query(new VectorQuery(
            new Vector([1.0, 0.0]),
            DistanceMetric::DotProduct,
            maxResults: 1,
        ))->hits->first();

        expect($hit?->id)->toBe('best')
            ->and($hit?->score->higherIsBetter())->toBeTrue()
            ->and($hit?->score->value)->toBeGreaterThan(0.0);
    } finally {
        $store->drop();
    }
})->skip(retrievalTypesenseEndpoint() === '', 'RETRIEVAL_TYPESENSE_URL is not configured');

function retrievalTypesenseEndpoint(): string
{
    $endpoint = getenv('RETRIEVAL_TYPESENSE_URL');

    return is_string($endpoint) ? $endpoint : '';
}

function retrievalTypesenseApiKey(): string
{
    $apiKey = getenv('RETRIEVAL_TYPESENSE_API_KEY');

    return is_string($apiKey) ? $apiKey : '';
}

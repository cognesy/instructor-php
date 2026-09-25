<?php

declare(strict_types=1);

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\ScanRequest;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\Meilisearch\MeilisearchConfig;
use Cognesy\Retrieval\Drivers\Meilisearch\MeilisearchStore;
use Cognesy\Retrieval\Filter\MetadataEquals;
use Cognesy\Retrieval\Query\VectorQuery;

it('roundtrips generic records through a live Meilisearch index', function () {
    $store = new MeilisearchStore(
        new MeilisearchConfig(
            retrievalMeilisearchEndpoint(),
            'retrieval_test_'.bin2hex(random_bytes(6)),
            2,
            apiKey: retrievalMeilisearchApiKey(),
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
})->skip(retrievalMeilisearchEndpoint() === '', 'RETRIEVAL_MEILISEARCH_URL is not configured');

function retrievalMeilisearchEndpoint(): string
{
    $endpoint = getenv('RETRIEVAL_MEILISEARCH_URL');

    return is_string($endpoint) ? $endpoint : '';
}

function retrievalMeilisearchApiKey(): string
{
    $apiKey = getenv('RETRIEVAL_MEILISEARCH_API_KEY');

    return is_string($apiKey) ? $apiKey : '';
}

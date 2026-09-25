<?php

declare(strict_types=1);

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\ScanRequest;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\Qdrant\QdrantConfig;
use Cognesy\Retrieval\Drivers\Qdrant\QdrantStore;
use Cognesy\Retrieval\Query\VectorQuery;

it('roundtrips generic record ids through a live Qdrant collection', function () {
    $endpoint = retrievalQdrantEndpoint();
    $collection = 'retrieval_test_'.bin2hex(random_bytes(6));
    $store = new QdrantStore(
        new QdrantConfig($endpoint, $collection, 2),
        HttpClient::default(),
    );
    $store->setup();
    try {
        $store->upsert(VectorDocuments::of(
            new VectorDocument('arbitrary-id', new Vector([1.0, 0.0]), content: 'near'),
            new VectorDocument(-7, new Vector([0.0, 1.0]), content: 'far'),
        ));

        $page = $store->query(new VectorQuery(new Vector([1.0, 0.0])));
        $fetched = $store->fetch(DocumentIds::of('arbitrary-id', -7), Projection::default());
        $scan = $store->scan(new ScanRequest(1));

        expect($page->hits->first()?->id)->toBe('arbitrary-id')
            ->and($page->hits->first()?->vector)->toBeNull()
            ->and(array_map(static fn ($document) => $document->id, $fetched->all()))
            ->toContain('arbitrary-id', -7)
            ->and($scan->documents->count())->toBe(1)
            ->and($scan->continuation)->not->toBeNull();

        $store->remove(DocumentIds::of('arbitrary-id'));
        expect($store->fetch(DocumentIds::of('arbitrary-id'), Projection::default())->count())->toBe(0);
    } finally {
        $store->drop();
    }
})->skip(retrievalQdrantEndpoint() === '', 'RETRIEVAL_QDRANT_URL is not configured');

function retrievalQdrantEndpoint(): string
{
    $endpoint = getenv('RETRIEVAL_QDRANT_URL');

    return is_string($endpoint) ? $endpoint : '';
}

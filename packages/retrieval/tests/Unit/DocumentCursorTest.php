<?php

declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Cursor\DocumentCursor;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\ScanRequest;
use Cognesy\Retrieval\Data\StoreContinuation;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;

it('traverses bounded scan pages without returning vectors by default', function () {
    $store = new InMemoryStore;
    $documents = [];
    for ($index = 0; $index < 103; $index++) {
        $documents[] = new VectorDocument($index, new Vector([(float) $index, 1.0]));
    }
    $store->upsert(new VectorDocuments($documents));
    $cursor = new DocumentCursor($store, new ScanRequest(pageSize: 7));

    $ids = [];
    foreach ($cursor as $document) {
        $ids[] = $document->id;
        expect($document->vector)->toBeNull();
    }

    expect($ids)->toBe(range(0, 102))
        ->and(fn () => iterator_to_array($cursor))->toThrow(LogicException::class);
});

it('stops before requesting another page when closed early', function () {
    $store = new InMemoryStore;
    $documents = [];
    for ($index = 0; $index < 20; $index++) {
        $documents[] = new VectorDocument($index, new Vector([(float) $index, 1.0]));
    }
    $store->upsert(new VectorDocuments($documents));
    $cursor = new DocumentCursor($store, new ScanRequest(5, projection: new Projection(vector: true)));

    $seen = 0;
    foreach ($cursor as $document) {
        $seen++;
        expect($document->vector)->not->toBeNull();
        if ($seen === 2) {
            $cursor->close();
        }
    }

    expect($seen)->toBe(2)
        ->and($cursor->closed())->toBeTrue();
});

it('rejects a continuation owned by another driver', function () {
    $store = new InMemoryStore;

    expect(fn () => $store->scan(new ScanRequest(
        continuation: new StoreContinuation('qdrant', ['offset' => 1]),
    )))->toThrow(InvalidArgumentException::class);
});

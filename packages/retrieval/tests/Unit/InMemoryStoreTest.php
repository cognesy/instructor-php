<?php

declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Data\DistanceMetric;
use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;
use Cognesy\Retrieval\Filter\MetadataEquals;
use Cognesy\Retrieval\Query\VectorQuery;

it('stores precomputed vectors and returns bounded ranked hits', function () {
    $store = new InMemoryStore;
    $write = $store->upsert(VectorDocuments::of(
        new VectorDocument('close', new Vector([1.0, 0.0]), ['tenant' => 'a'], 'first', 'space-v1'),
        new VectorDocument('far', new Vector([0.0, 1.0]), ['tenant' => 'a'], 'second', 'space-v1'),
        new VectorDocument('hidden', new Vector([0.9, 0.1]), ['tenant' => 'b'], 'third', 'space-v1'),
    ));

    $page = $store->query(new VectorQuery(
        vector: new Vector([1.0, 0.0]),
        maxResults: 1,
        filter: new MetadataEquals('tenant', 'a'),
        embeddingSpace: 'space-v1',
    ));

    expect($write->acknowledged)->toBe(3)
        ->and($page->hits->count())->toBe(1)
        ->and($page->hits->first()?->id)->toBe('close')
        ->and($page->hits->first()?->vector)->toBeNull()
        ->and($page->hits->first()?->rank)->toBe(1);
});

it('projects vectors only when explicitly requested', function () {
    $store = new InMemoryStore;
    $store->upsert(VectorDocuments::of(
        new VectorDocument(1, new Vector([1.0, 0.0])),
    ));

    $page = $store->query(new VectorQuery(
        vector: new Vector([1.0, 0.0]),
        projection: new Projection(vector: true),
    ));

    expect($page->hits->first()?->vector)->not->toBeNull();
});

it('supports deterministic replacement removal and clear', function () {
    $store = new InMemoryStore;
    $store->upsert(VectorDocuments::of(
        new VectorDocument('same', new Vector([1.0, 0.0]), content: 'old'),
    ));
    $store->upsert(VectorDocuments::of(
        new VectorDocument('same', new Vector([0.0, 1.0]), content: 'new'),
    ));

    $replacement = $store->query(new VectorQuery(new Vector([0.0, 1.0])));
    $removed = $store->remove(DocumentIds::of('same', 'missing'));
    $cleared = $store->clear();

    expect($replacement->hits->count())->toBe(1)
        ->and($replacement->hits->first()?->content)->toBe('new')
        ->and($removed->acknowledged)->toBe(1)
        ->and($removed->missing)->toBe(1)
        ->and($cleared->acknowledged)->toBe(0);
});

it('uses ascending distance for euclidean stores', function () {
    $store = new InMemoryStore(DistanceMetric::Euclidean);
    $store->upsert(VectorDocuments::of(
        new VectorDocument('far', new Vector([10.0, 10.0])),
        new VectorDocument('close', new Vector([1.0, 1.0])),
    ));

    $page = $store->query(new VectorQuery(
        vector: new Vector([0.0, 0.0]),
        metric: DistanceMetric::Euclidean,
    ));

    expect($page->hits->first()?->id)->toBe('close')
        ->and($page->hits->first()?->score->higherIsBetter())->toBeFalse();
});

it('rejects invalid vectors dimensions spaces and metrics', function () {
    expect(fn () => new VectorDocument('empty', new Vector([])))
        ->toThrow(InvalidArgumentException::class);

    $store = new InMemoryStore;
    $store->upsert(VectorDocuments::of(
        new VectorDocument('one', new Vector([1.0, 0.0]), embeddingSpace: 'space-v1'),
    ));

    expect(fn () => $store->upsert(VectorDocuments::of(
        new VectorDocument('wrong-dimension', new Vector([1.0])),
    )))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $store->query(new VectorQuery(
            new Vector([1.0, 0.0]),
            embeddingSpace: 'space-v2',
        )))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $store->query(new VectorQuery(
            new Vector([1.0, 0.0]),
            metric: DistanceMetric::DotProduct,
            embeddingSpace: 'space-v1',
        )))->toThrow(InvalidArgumentException::class);
});

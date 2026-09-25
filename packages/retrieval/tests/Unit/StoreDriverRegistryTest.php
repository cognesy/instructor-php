<?php

declare(strict_types=1);

use Cognesy\Retrieval\Config\StoreConfig;
use Cognesy\Retrieval\Config\StoreProvider;
use Cognesy\Retrieval\Creation\StoreDriverRegistry;
use Cognesy\Retrieval\Creation\StoreFactory;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;
use Cognesy\Retrieval\Drivers\Meilisearch\MeilisearchStore;
use Cognesy\Retrieval\Drivers\Milvus\MilvusStore;
use Cognesy\Retrieval\Drivers\Typesense\TypesenseStore;
use Cognesy\Retrieval\Drivers\Weaviate\WeaviateStore;

it('provides the bundled memory driver', function () {
    $registry = StoreDriverRegistry::default();

    expect($registry->has('memory'))->toBeTrue()
        ->and($registry->driverNames())->toContain('memory')
        ->and($registry->makeDriver('memory', new StoreConfig))->toBeInstanceOf(InMemoryStore::class);
});

it('provides the bundled Typesense driver', function () {
    $registry = StoreDriverRegistry::default();
    $driver = $registry->makeDriver('typesense', new StoreConfig('typesense', options: [
        'endpoint' => 'http://typesense:8108',
        'collection' => 'docs',
        'dimensions' => 2,
    ]));

    expect($registry->has('typesense'))->toBeTrue()
        ->and($driver)->toBeInstanceOf(TypesenseStore::class);
});

it('provides the bundled Meilisearch driver', function () {
    $registry = StoreDriverRegistry::default();
    $driver = $registry->makeDriver('meilisearch', new StoreConfig('meilisearch', options: [
        'endpoint' => 'http://meilisearch:7700',
        'index' => 'docs',
        'dimensions' => 2,
    ]));

    expect($registry->has('meilisearch'))->toBeTrue()
        ->and($driver)->toBeInstanceOf(MeilisearchStore::class);
});

it('provides the bundled Weaviate driver', function () {
    $registry = StoreDriverRegistry::default();
    $driver = $registry->makeDriver('weaviate', new StoreConfig('weaviate', options: [
        'endpoint' => 'http://weaviate:8080',
        'collection' => 'Documents',
        'dimensions' => 2,
    ]));

    expect($registry->has('weaviate'))->toBeTrue()
        ->and($driver)->toBeInstanceOf(WeaviateStore::class);
});

it('provides the bundled Milvus driver', function () {
    $registry = StoreDriverRegistry::default();
    $driver = $registry->makeDriver('milvus', new StoreConfig('milvus', options: [
        'endpoint' => 'http://milvus:19530',
        'collection' => 'documents',
        'dimensions' => 2,
    ]));

    expect($registry->has('milvus'))->toBeTrue()
        ->and($driver)->toBeInstanceOf(MilvusStore::class);
});

it('prefers an explicitly injected store driver', function () {
    $driver = new InMemoryStore;
    $provider = (new StoreProvider)->withDriver($driver);

    expect(StoreFactory::fromProvider($provider))->toBe($driver);
});

<?php

declare(strict_types=1);

use Cognesy\Retrieval\Config\StoreConfig;
use Cognesy\Retrieval\Creation\StoreDriverRegistry;
use Cognesy\Retrieval\Drivers\Pgvector\PgvectorConfig;

it('validates and quotes schema-qualified Pgvector tables', function () {
    $config = new PgvectorConfig('retrieval.documents', 1536);

    expect($config->quotedTable())->toBe('"retrieval"."documents"');
});

it('rejects unsafe Pgvector identifiers', function (string $table) {
    expect(fn () => new PgvectorConfig($table, 3))->toThrow(InvalidArgumentException::class);
})->with(['docs;drop table users', 'schema.docs.extra', 'docs-name', '']);

it('rejects unsupported relaxed ordering instead of overstating capabilities', function () {
    expect(fn () => new PgvectorConfig('documents', 3, relaxedOrdering: true))
        ->toThrow(InvalidArgumentException::class, 'relaxed ordering is not supported');
});

it('requires an explicitly injected PDO instance', function () {
    $config = new StoreConfig('pgvector', options: [
        'table' => 'documents',
        'dimensions' => 3,
    ]);

    expect(fn () => StoreDriverRegistry::default()->makeDriver('pgvector', $config))
        ->toThrow(InvalidArgumentException::class, 'requires a PDO instance');
});

<?php

declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\Pgvector\PgvectorConfig;
use Cognesy\Retrieval\Drivers\Pgvector\PgvectorStore;
use Cognesy\Retrieval\Query\VectorQuery;

it('roundtrips vectors through PostgreSQL with pgvector', function () {
    $dsn = retrievalPgvectorDsn();
    $pdo = new PDO(
        $dsn,
        getenv('RETRIEVAL_PGVECTOR_USER') ?: null,
        getenv('RETRIEVAL_PGVECTOR_PASSWORD') ?: null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $table = 'retrieval_test_'.bin2hex(random_bytes(6));
    $store = new PgvectorStore(new PgvectorConfig($table, 2), $pdo);
    $store->setup();
    try {
        $store->upsert(VectorDocuments::of(
            new VectorDocument('near', new Vector([1.0, 0.0]), content: 'near'),
            new VectorDocument('far', new Vector([0.0, 1.0]), content: 'far'),
        ));
        $page = $store->query(new VectorQuery(new Vector([1.0, 0.0])));

        expect($page->hits->first()?->id)->toBe('near')
            ->and($page->hits->first()?->vector)->toBeNull();
    } finally {
        $store->drop();
    }
})->skip(retrievalPgvectorDsn() === '', 'RETRIEVAL_PGVECTOR_DSN is not configured');

it('installs an HNSW index when approximate search is configured', function () {
    $dsn = retrievalPgvectorDsn();
    $pdo = new PDO(
        $dsn,
        getenv('RETRIEVAL_PGVECTOR_USER') ?: null,
        getenv('RETRIEVAL_PGVECTOR_PASSWORD') ?: null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $table = 'retrieval_ann_'.bin2hex(random_bytes(6));
    $store = new PgvectorStore(new PgvectorConfig($table, 2, approximate: true), $pdo);
    $store->setup();
    try {
        $statement = $pdo->prepare('SELECT indexname FROM pg_indexes WHERE tablename = :table');
        $statement->execute(['table' => $table]);
        $indexes = $statement->fetchAll(PDO::FETCH_COLUMN);

        expect($store->capabilities()->approximate)->toBeTrue()
            ->and($store->capabilities()->exact)->toBeFalse()
            ->and($store->capabilities()->ordering)->toBe('strict')
            ->and($indexes)->toContain($table.'_embedding_hnsw_idx');
    } finally {
        $store->drop();
    }
})->skip(retrievalPgvectorDsn() === '', 'RETRIEVAL_PGVECTOR_DSN is not configured');

function retrievalPgvectorDsn(): string
{
    $dsn = getenv('RETRIEVAL_PGVECTOR_DSN');

    return is_string($dsn) ? $dsn : '';
}

<?php

declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Cursor\DocumentCursor;
use Cognesy\Retrieval\Data\ScanRequest;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;

it('keeps cursor-owned memory bounded while traversing one hundred thousand records', function () {
    $store = new InMemoryStore;
    for ($start = 0; $start < 100_000; $start += 1000) {
        $batch = [];
        for ($index = $start; $index < $start + 1000; $index++) {
            $batch[] = new VectorDocument($index, new Vector([(float) $index, 1.0]));
        }
        $store->upsert(new VectorDocuments($batch));
    }
    unset($batch);
    gc_collect_cycles();
    $before = memory_get_usage(true);

    $count = 0;
    foreach (new DocumentCursor($store, new ScanRequest(250)) as $document) {
        $count += $document->id >= 0 ? 1 : 0;
    }
    gc_collect_cycles();
    $retained = memory_get_usage(true) - $before;

    expect($count)->toBe(100_000)
        ->and($retained)->toBeLessThan(8 * 1024 * 1024);
});

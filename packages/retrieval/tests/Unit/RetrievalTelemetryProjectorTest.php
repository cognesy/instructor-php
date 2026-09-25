<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;
use Cognesy\Retrieval\Query\VectorQuery;
use Cognesy\Retrieval\Retrieval;
use Cognesy\Retrieval\Telemetry\RetrievalTelemetryProjector;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;

it('projects a retrieval lifecycle as one trace span', function () {
    $exporter = new OtelExporter;
    $telemetry = new Telemetry(new TraceRegistry, $exporter);
    $events = new EventDispatcher('retrieval-telemetry-test');
    (new RuntimeEventBridge(new RetrievalTelemetryProjector($telemetry)))->attachTo($events);

    Retrieval::fromStore(new InMemoryStore, $events)
        ->withQuery(new VectorQuery(new Vector([1.0, 0.0])))
        ->get();

    $observations = $exporter->observations();
    expect($observations)->toHaveCount(1)
        ->and($observations[0]->name())->toBe('retrieval.query')
        ->and($observations[0]->attributes()->toArray())
        ->toMatchArray([
            'retrieval.driver' => 'custom',
            'retrieval.hit_count' => 0,
        ]);
});

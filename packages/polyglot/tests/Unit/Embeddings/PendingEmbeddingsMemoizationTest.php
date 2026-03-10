<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;
use Cognesy\Polyglot\Tests\Support\FakeEmbeddingsDriver;

/**
 * Regression: PendingEmbeddings::get() must memoize the response.
 *
 * Before the fix, makeResponse() was public and bypassed memoization.
 * Now it is private, and get() caches the result so the driver is
 * called exactly once regardless of how many times get() is invoked.
 */
it('memoizes the response across multiple get() calls', function () {
    $driver = new FakeEmbeddingsDriver([
        new EmbeddingsResponse([new Vector(values: [0.1, 0.2], id: 0)]),
    ]);

    $request = new EmbeddingsRequest(input: ['hello']);
    $pending = new PendingEmbeddings($request, $driver, new EventDispatcher());

    $first = $pending->get();
    $second = $pending->get();
    $third = $pending->get();

    // Same instance returned each time
    expect($second)->toBe($first);
    expect($third)->toBe($first);
    // Driver called exactly once
    expect($driver->handleCalls)->toBe(1);
});

it('returns vectors from memoized response', function () {
    $driver = new FakeEmbeddingsDriver([
        new EmbeddingsResponse([
            new Vector(values: [0.5, 0.6, 0.7], id: 0),
            new Vector(values: [0.8, 0.9, 1.0], id: 1),
        ]),
    ]);

    $request = new EmbeddingsRequest(input: ['hello', 'world']);
    $pending = new PendingEmbeddings($request, $driver, new EventDispatcher());

    $response = $pending->get();
    expect($response->vectors())->toHaveCount(2);
    expect($response->first()->values())->toBe([0.5, 0.6, 0.7]);
});

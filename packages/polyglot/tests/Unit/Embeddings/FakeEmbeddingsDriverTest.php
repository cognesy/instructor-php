<?php declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Tests\Support\FakeEmbeddingsDriver;

it('returns queued embeddings responses and records requests', function () {
    $driver = new FakeEmbeddingsDriver([
        new EmbeddingsResponse([new Vector(values: [0.1, 0.2], id: 0)]),
        new EmbeddingsResponse([new Vector(values: [0.3, 0.4], id: 1)]),
    ]);

    $firstRequest = new EmbeddingsRequest(input: ['first']);
    $secondRequest = new EmbeddingsRequest(input: ['second']);

    $driver->handle($firstRequest);
    $first = $driver->fromData([]);

    $driver->handle($secondRequest);
    $second = $driver->fromData([]);

    expect($driver->handleCalls)->toBe(2)
        ->and($driver->requests)->toHaveCount(2)
        ->and($driver->requests[0])->toBe($firstRequest)
        ->and($driver->requests[1])->toBe($secondRequest)
        ->and($first?->first()?->values())->toBe([0.1, 0.2])
        ->and($second?->first()?->values())->toBe([0.3, 0.4]);
});

it('supports callback-driven embeddings responses', function () {
    $driver = new FakeEmbeddingsDriver(
        onResponse: static fn(EmbeddingsRequest $request): EmbeddingsResponse => new EmbeddingsResponse([
            new Vector(values: [count($request->inputs())], id: 0),
        ]),
    );

    $driver->handle(new EmbeddingsRequest(input: ['a', 'b']));
    $response = $driver->fromData([]);

    expect($response?->first()?->values())->toBe([2]);
});

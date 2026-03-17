<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Events\PartialInferenceDeltaCreated;

it('serializes streamed deltas as direct minimal event data', function () {
    $event = new PartialInferenceDeltaCreated([
        'executionId' => 'inference-1',
        'contentDelta' => 'Hel',
    ]);

    $payload = $event->toArray();

    expect($payload['data'])->toBe([
        'executionId' => 'inference-1',
        'contentDelta' => 'Hel',
    ]);
    expect($event->__toString())->toContain('"contentDelta"');
});

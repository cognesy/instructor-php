<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Events\PartialInferenceDeltaCreated;

it('serializes streamed deltas without requiring accumulated response snapshots', function () {
    $delta = new PartialInferenceDelta(
        contentDelta: 'Hel',
        reasoningContentDelta: 'thinking',
        toolName: 'extract_data',
        toolArgs: '{"name":"Ann"}',
        finishReason: 'stop',
        usage: new Usage(inputTokens: 10, outputTokens: 2),
        value: ['name' => 'Ann'],
    );

    $event = new PartialInferenceDeltaCreated('inference-1', $delta);
    $payload = $event->toArray();

    expect($payload['executionId'])->toBe('inference-1');
    expect($payload['partialInferenceDelta'])->toMatchArray([
        'contentDelta' => 'Hel',
        'reasoningContentDelta' => 'thinking',
        'toolId' => '',
        'toolName' => 'extract_data',
        'toolArgs' => '{"name":"Ann"}',
        'finishReason' => 'stop',
        'usage' => [
            'input' => 10,
            'output' => 2,
            'cacheWrite' => 0,
            'cacheRead' => 0,
            'reasoning' => 0,
            'pricing' => null,
        ],
        'hasValue' => true,
        'value' => ['name' => 'Ann'],
    ]);

    expect($event->__toString())->toContain('"partialInferenceDelta"');
});

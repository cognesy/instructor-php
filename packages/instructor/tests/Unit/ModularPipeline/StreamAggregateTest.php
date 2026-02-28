<?php declare(strict_types=1);

use Cognesy\Instructor\ResponseIterators\ModularPipeline\Aggregation\StreamAggregate;
use Cognesy\Instructor\Tests\Support\FakeStreamFactory;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

test('merge accumulates content usage and finish reason', function () {
    $value = (object) ['id' => 1];

    [$partial1, $partial2] = FakeStreamFactory::from(
        (new PartialInferenceResponse(
            contentDelta: 'hello ',
            usage: new Usage(inputTokens: 2, outputTokens: 3),
        ))->withValue($value),
        new PartialInferenceResponse(
            contentDelta: 'world',
            finishReason: 'stop',
            usage: new Usage(inputTokens: 1, outputTokens: 2),
        ),
    );

    $aggregate = StreamAggregate::empty()
        ->merge($partial1)
        ->merge($partial2);

    expect($aggregate->content)->toBe('hello world');
    expect($aggregate->frameCount)->toBe(2);
    expect($aggregate->latestValue)->toBe($value);
    expect($aggregate->usage->input())->toBe(3);
    expect($aggregate->usage->output())->toBe(5);
    expect($aggregate->finishReason())->toBe('stop');
    expect($aggregate->partial()->content())->toBe('hello world');
});

test('merge always keeps latest partial snapshot', function () {
    [$partial] = FakeStreamFactory::from(
        new PartialInferenceResponse(contentDelta: 'data', usage: Usage::none())
    );
    $aggregate = StreamAggregate::empty()->merge($partial);

    expect($aggregate->partial()->content())->toBe('data');
});

test('toInferenceResponse falls back to tracked partial content', function () {
    $partial = (new PartialInferenceResponse(contentDelta: '{}', usage: Usage::none()))
        ->withContent('{}');
    $aggregate = new StreamAggregate(
        content: '',
        latestValue: null,
        finishReason: null,
        usage: Usage::none(),
        frameCount: 1,
        partial: $partial,
    );

    $response = $aggregate->toInferenceResponse();

    expect($response->content())->toBe('{}');
    expect($response->finishReason()->value)->toBe('stop');
});

test('toInferenceResponse falls back to serialized latest value', function () {
    $aggregate = new StreamAggregate(
        content: '',
        latestValue: ['x' => 1],
        finishReason: null,
        usage: Usage::none(),
        frameCount: 1,
        partial: PartialInferenceResponse::empty(),
    );

    $response = $aggregate->toInferenceResponse();

    expect($response->content())->toContain('"x":1');
    expect($response->finishReason()->value)->toBe('stop');
});

<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseUpdated;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceRequestDriver;
use Cognesy\Events\Dispatchers\EventDispatcher;

class StreamUserStructA { public int $age; public string $name; }

it('dispatches per-chunk updates immediately when streaming', function () {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"name":"Ann"', usage: new Usage(outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: ',"age":', usage: new Usage(outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: '30}', finishReason: 'stop', usage: new Usage(outputTokens: 1)),
    ];

    $driver = new FakeInferenceRequestDriver(
        responses: [],
        streamBatches: [ $chunks ]
    );

    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function ($e) use (&$captured) { $captured[] = $e; });

    $stream = (new StructuredOutput(events: $events))
        ->withDriver($driver)
        ->with(
            messages: 'Extract user',
            responseModel: StreamUserStructA::class,
            mode: OutputMode::Json,
        )
        ->stream();

    $iter = $stream->responses();

    // Step 1
    expect($iter->valid())->toBeTrue();
    $first = $iter->current();
    // One update event, no generated event yet
    $types = array_map(fn($e) => get_class($e), $captured);
    expect(array_filter($types, fn($t) => $t === StructuredOutputResponseUpdated::class))->toHaveCount(1);
    expect(array_filter($types, fn($t) => $t === StructuredOutputResponseGenerated::class))->toHaveCount(0);

    // Step 2
    $iter->next();
    expect($iter->valid())->toBeTrue();
    $second = $iter->current();
    $types = array_map(fn($e) => get_class($e), $captured);
    expect(array_filter($types, fn($t) => $t === StructuredOutputResponseUpdated::class))->toHaveCount(2);
    expect(array_filter($types, fn($t) => $t === StructuredOutputResponseGenerated::class))->toHaveCount(0);

    // Step 3 (final)
    $iter->next();
    expect($iter->valid())->toBeTrue();
    $third = $iter->current();
    $iter->next(); // advance past last

    // All 3 updates present, but Generated only fires from finalResponse(), not from responses()
    $types = array_map(fn($e) => get_class($e), $captured);
    expect(array_filter($types, fn($t) => $t === StructuredOutputResponseUpdated::class))->toHaveCount(3);
    expect(array_filter($types, fn($t) => $t === StructuredOutputResponseGenerated::class))->toHaveCount(0);

    // Now explicitly request final response — this triggers the Generated event
    $stream->finalResponse();
    $types = array_map(fn($e) => get_class($e), $captured);
    expect(array_filter($types, fn($t) => $t === StructuredOutputResponseGenerated::class))->toHaveCount(1);
});

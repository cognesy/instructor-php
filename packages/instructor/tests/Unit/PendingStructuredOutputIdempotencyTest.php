<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class IdempotencyStruct { public string $name; public int $age; }

/**
 * Regression: PendingStructuredOutput::response() called multiple times
 * after stream() must not re-dispatch StructuredOutputResponseGenerated.
 *
 * Before the fix, getResponse() always delegated to cachedStream->finalResponse()
 * which re-iterated streamResponses() and dispatched the event again on every call.
 * The fix caches the response from the stream-first path so subsequent calls
 * short-circuit.
 */
it('does not re-emit StructuredOutputResponseGenerated on repeated response() calls after stream', function () {
    $events = new EventDispatcher();

    $generatedCount = 0;
    $events->addListener(StructuredOutputResponseGenerated::class, function () use (&$generatedCount): void {
        $generatedCount++;
    });

    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"name":"Alice"', usage: new Usage(outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: ',"age":', usage: new Usage(outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: '30}', finishReason: 'stop', usage: new Usage(outputTokens: 1)),
    ];

    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [$chunks],
    );

    $pending = (new StructuredOutput(events: $events))
        ->withDriver($driver)
        ->with(
            messages: 'Extract user',
            responseModel: IdempotencyStruct::class,
            mode: OutputMode::Json,
        )
        ->create();

    // Consume the stream first
    $stream = $pending->stream();
    $final = $stream->finalValue();
    expect($final)->toBeInstanceOf(IdempotencyStruct::class);
    expect($final->name)->toBe('Alice');
    expect($final->age)->toBe(30);

    // finalValue() consumed streamResponses() which dispatched the event once
    $countAfterStream = $generatedCount;
    expect($countAfterStream)->toBe(1);

    // First response() call — may delegate to cachedStream->finalResponse()
    $first = $pending->response();
    $countAfterFirstResponse = $generatedCount;

    // Second response() call — must hit cachedResponse early return, no new events
    $second = $pending->response();
    expect($generatedCount)->toBe($countAfterFirstResponse);

    // Third response() call — still idempotent
    $third = $pending->response();
    expect($generatedCount)->toBe($countAfterFirstResponse);

    // All return the same content
    expect($first->content())->toBe($second->content());
    expect($second->content())->toBe($third->content());
});

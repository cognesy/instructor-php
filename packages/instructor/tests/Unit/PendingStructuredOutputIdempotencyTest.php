<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class IdempotencyStruct { public string $name; public int $age; }

/**
 * Regression: stream()->finalResponse() then response() must emit
 * StructuredOutputResponseGenerated exactly once total.
 *
 * Before the fix, StructuredOutputStream::streamResponses() dispatched
 * the generated event at the end of every pass. When PendingStructuredOutput
 * delegated to cachedStream->finalResponse() on the first response() call,
 * it re-iterated streamResponses() and dispatched the event a second time.
 *
 * The fix adds a $generated flag in StructuredOutputStream so the event
 * fires only on the first streamResponses() pass, and PendingStructuredOutput
 * caches the result so subsequent response() calls short-circuit entirely.
 */
it('emits StructuredOutputResponseGenerated exactly once across stream and response calls', function () {
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

    $driver = new FakeInferenceRequestDriver(
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

    // Consume the stream — dispatches generated event once
    $stream = $pending->stream();
    $final = $stream->finalResponse();
    expect($final->content())->toContain('Alice');
    expect($generatedCount)->toBe(1);

    // response() delegates to cachedStream->finalResponse() — must not dispatch again
    $first = $pending->response();
    expect($generatedCount)->toBe(1);

    // Repeated response() calls — still exactly 1
    $second = $pending->response();
    expect($generatedCount)->toBe(1);

    $third = $pending->response();
    expect($generatedCount)->toBe(1);

    // All return the same content
    expect($first->content())->toBe($final->content());
    expect($second->content())->toBe($final->content());
    expect($third->content())->toBe($final->content());
});

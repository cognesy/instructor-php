<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Examples\Extraction\Person;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

/**
 * Gate 2 (research/v2-cleanup-plan/01): characterization matrix.
 *
 * Pins USER-OBSERVABLE behavior across {Json, JsonSchema, MdJson, Tools} ×
 * {sync, stream} × {clean, retry-then-succeed}: final values, sequence yields,
 * and which event classes fire on golden paths. Every cleanup stone must keep
 * this suite green. Event sets were captured empirically from current behavior
 * (2026-07-06) — they are the contract, not an aspiration.
 */

// Person (name: Length min 3, age: PositiveOrZero) drives validation retries.
const CM_VALID_JSON = '{"name":"Jason","age":28}';
const CM_INVALID_JSON = '{"name":"JX","age":-28}';

const CM_SYNC_EVENT_CORE = [
    \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived::class,
    \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted::class,
    \Cognesy\Instructor\Events\Extraction\ExtractionCompleted::class,
    \Cognesy\Instructor\Events\Response\ResponseDeserialized::class,
    \Cognesy\Instructor\Events\Response\ResponseValidated::class,
    \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated::class,
];

const CM_STREAM_EVENT_CORE = [
    \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted::class,
    \Cognesy\Instructor\Events\Streaming\ChunkReceived::class,
    \Cognesy\Instructor\Events\Streaming\PartialResponseGenerated::class,
    \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseUpdated::class,
    \Cognesy\Instructor\Events\Streaming\StreamedResponseReceived::class,
    \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated::class,
];

function cmWrapContent(OutputMode $mode, string $json): string {
    return match ($mode) {
        OutputMode::MdJson => "Here you go:\n```json\n{$json}\n```\n",
        default => $json,
    };
}

function cmSyncResponse(OutputMode $mode, string $json): InferenceResponse {
    return match ($mode) {
        OutputMode::Tools => new InferenceResponse(
            content: '',
            finishReason: 'stop',
            toolCalls: new ToolCalls(new ToolCall('extract', json_decode($json, true))),
        ),
        default => new InferenceResponse(content: cmWrapContent($mode, $json)),
    };
}

/** @return list<PartialInferenceDelta> */
function cmStreamBatch(OutputMode $mode, string $json): array {
    if ($mode === OutputMode::Tools) {
        $chunks = str_split($json, 8);
        $deltas = [new PartialInferenceDelta(toolName: 'extract', toolArgs: array_shift($chunks))];
        foreach ($chunks as $chunk) {
            $deltas[] = new PartialInferenceDelta(toolArgs: $chunk);
        }
        $deltas[] = new PartialInferenceDelta(finishReason: 'tool_calls');
        return $deltas;
    }

    $deltas = array_map(
        static fn(string $chunk): PartialInferenceDelta => new PartialInferenceDelta(contentDelta: $chunk),
        str_split(cmWrapContent($mode, $json), 8),
    );
    $deltas[] = new PartialInferenceDelta(finishReason: 'stop');
    return $deltas;
}

function cmRecordedEvents(EventDispatcher $events, array &$recorded): void {
    $events->wiretap(static function (object $event) use (&$recorded): void {
        $recorded[] = get_class($event);
    });
}

function cmExpectEvents(array $recorded, array $expectedClasses): void {
    foreach ($expectedClasses as $class) {
        expect(in_array($class, $recorded, true))
            ->toBeTrue('Expected event not fired: ' . $class);
    }
}

dataset('output modes', [
    'Json' => [OutputMode::Json],
    'JsonSchema' => [OutputMode::JsonSchema],
    'MdJson' => [OutputMode::MdJson],
    'Tools' => [OutputMode::Tools],
]);

// ── clean × sync ─────────────────────────────────────────────────────

it('sync clean: returns typed object with expected values', function (OutputMode $mode) {
    $driver = new FakeInferenceDriver(responses: [cmSyncResponse($mode, CM_VALID_JSON)]);
    $events = new EventDispatcher();
    $recorded = [];
    cmRecordedEvents($events, $recorded);

    $person = (new StructuredOutput(makeStructuredRuntime(driver: $driver, outputMode: $mode, events: $events)))
        ->withMessages('extract person')
        ->withResponseClass(Person::class)
        ->get();

    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($driver->responseCalls)->toBe(1);
    cmExpectEvents($recorded, CM_SYNC_EVENT_CORE);
})->with('output modes');

// ── clean × stream ───────────────────────────────────────────────────

it('stream clean: yields partials and a complete final value', function (OutputMode $mode) {
    $driver = new FakeInferenceDriver(streamBatches: [cmStreamBatch($mode, CM_VALID_JSON)]);
    $events = new EventDispatcher();
    $recorded = [];
    cmRecordedEvents($events, $recorded);

    $stream = (new StructuredOutput(makeStructuredRuntime(driver: $driver, outputMode: $mode, events: $events)))
        ->withMessages('extract person')
        ->withResponseClass(Person::class)
        ->withStreaming(true)
        ->stream();

    $partials = 0;
    foreach ($stream->partials() as $partial) {
        $partials++;
    }
    $final = $stream->finalValue();

    expect($final)->toBeInstanceOf(Person::class);
    expect($final->name)->toBe('Jason');
    expect($final->age)->toBe(28);
    expect($driver->streamCalls)->toBe(1);

    // Characterized limitation: MdJson streams fenced prose, so no typed
    // partial materializes mid-stream — only the final re-extract handles
    // fences. Typed-partial events fire for all other modes.
    if ($mode !== OutputMode::MdJson) {
        expect($partials)->toBeGreaterThan(0);
        cmExpectEvents($recorded, CM_STREAM_EVENT_CORE);
    } else {
        cmExpectEvents($recorded, [
            \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted::class,
            \Cognesy\Instructor\Events\Streaming\ChunkReceived::class,
            \Cognesy\Instructor\Events\Streaming\StreamedResponseReceived::class,
            \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated::class,
        ]);
    }
})->with('output modes');

// ── retry-then-succeed × sync ────────────────────────────────────────

it('sync retry: validation failure triggers one retry then succeeds', function (OutputMode $mode) {
    $driver = new FakeInferenceDriver(responses: [
        cmSyncResponse($mode, CM_INVALID_JSON),
        cmSyncResponse($mode, CM_VALID_JSON),
    ]);
    $events = new EventDispatcher();
    $recorded = [];
    cmRecordedEvents($events, $recorded);

    $person = (new StructuredOutput(makeStructuredRuntime(
            driver: $driver, outputMode: $mode, events: $events, maxRetries: 2,
        )))
        ->withMessages('extract person')
        ->withResponseClass(Person::class)
        ->get();

    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($driver->responseCalls)->toBe(2);
    expect(in_array(\Cognesy\Instructor\Events\Response\ResponseValidationFailed::class, $recorded, true))->toBeTrue();
    cmExpectEvents($recorded, CM_SYNC_EVENT_CORE);
})->with('output modes');

// ── retry-then-succeed × stream ──────────────────────────────────────

it('stream retry: validation failure triggers one retry then succeeds', function (OutputMode $mode) {
    $driver = new FakeInferenceDriver(streamBatches: [
        cmStreamBatch($mode, CM_INVALID_JSON),
        cmStreamBatch($mode, CM_VALID_JSON),
    ]);
    $events = new EventDispatcher();
    $recorded = [];
    cmRecordedEvents($events, $recorded);

    $stream = (new StructuredOutput(makeStructuredRuntime(
            driver: $driver, outputMode: $mode, events: $events, maxRetries: 2,
        )))
        ->withMessages('extract person')
        ->withResponseClass(Person::class)
        ->withStreaming(true)
        ->stream();

    foreach ($stream->partials() as $partial) {}
    $final = $stream->finalValue();

    expect($final)->toBeInstanceOf(Person::class);
    expect($final->name)->toBe('Jason');
    expect($final->age)->toBe(28);
    expect($driver->streamCalls)->toBe(2);
    expect(in_array(\Cognesy\Instructor\Events\Response\ResponseValidationFailed::class, $recorded, true))->toBeTrue();
})->with('output modes');

// ── sequence streaming semantics ─────────────────────────────────────

class CharMatrixItem { public string $title = ''; }

it('stream sequence: yields each completed item exactly once', function () {
    $json = '{"list":[{"title":"A"},{"title":"B"},{"title":"C"}]}';
    $driver = new FakeInferenceDriver(streamBatches: [cmStreamBatch(OutputMode::Json, $json)]);

    $stream = (new StructuredOutput(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json)))
        ->withMessages('extract items')
        ->withResponseModel(Sequence::of(CharMatrixItem::class))
        ->withStreaming(true)
        ->stream();

    $titles = [];
    foreach ($stream->sequence() as $item) {
        $titles[] = $item->title;
    }

    expect($titles)->toBe(['A', 'B', 'C']);
    expect(count($stream->finalValue()))->toBe(3);
});

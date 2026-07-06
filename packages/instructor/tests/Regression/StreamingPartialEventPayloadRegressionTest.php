<?php declare(strict_types=1);

use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Events\Streaming\PartialResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseUpdated;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

class NonSerializableStreamingPartial
{
    public function __construct(
        public string $name,
    ) {}

    public function toArray(): array
    {
        throw new RuntimeException('partial value should not be normalized eagerly');
    }
}

it('emits incomplete structured outputs without eagerly serializing partial event payloads', function () {
    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [[
            new PartialInferenceDelta(value: new NonSerializableStreamingPartial('Ann')),
            new PartialInferenceDelta(value: new NonSerializableStreamingPartial('Anne')),
        ]],
    );

    $updates = [];
    $partials = [];
    $runtime = makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json)
        ->onEvent(StructuredOutputResponseUpdated::class, function (StructuredOutputResponseUpdated $event) use (&$updates): void {
            $updates[] = $event;
        })
        ->onEvent(PartialResponseGenerated::class, function (PartialResponseGenerated $event) use (&$partials): void {
            $partials[] = $event;
        });

    $stream = (new StructuredOutput($runtime))
        ->withMessages('ignored')
        ->withResponseClass(NonSerializableStreamingPartial::class)
        ->withStreaming()
        ->create()
        ->stream();

    $responses = iterator_to_array($stream->responses(), false);

    expect($responses)->toHaveCount(2);
    expect($responses[0]->isPartial())->toBeTrue();
    expect($responses[0]->value())->toBeInstanceOf(NonSerializableStreamingPartial::class);
    expect($responses[0]->value()->name)->toBe('Ann');

    expect($updates)->toHaveCount(2);
    expect($updates[0]->data)->toHaveKeys([
        'valueType',
        'hasValue',
        'contentLength',
        'toolCallCount',
    ]);
    expect($updates[0]->data['valueType'])->toBe(NonSerializableStreamingPartial::class);
    expect($updates[0]->data)->not()->toHaveKeys([
        'value',
        'content',
        'reasoningContent',
        'toolArgsSnapshot',
        'toolCalls',
    ]);
    expect($updates[0]->value())->toBe($responses[0]->value());
    expect(fn() => $updates[0]->serializedValue())->toThrow(RuntimeException::class);

    expect($partials)->toHaveCount(2);
    expect($partials[0]->partialResponse)->toBe($responses[0]->value());
    expect($partials[0]->data)->toBe([
        'valueType' => NonSerializableStreamingPartial::class,
        'hasValue' => true,
    ]);
    expect(fn() => $partials[0]->serializedValue())->toThrow(RuntimeException::class);
});

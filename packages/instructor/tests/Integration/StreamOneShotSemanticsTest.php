<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;

class StreamOneShotUser { public int $age; public string $name; }

it('throws when iterating responses a second time after stream completion', function () {
    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [[
            new PartialInferenceResponse(contentDelta: '{"name":"Ann"'),
            new PartialInferenceResponse(contentDelta: ',"age":30}', finishReason: 'stop'),
        ]],
    );

    $stream = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
        ->with(
            messages: 'Extract user',
            responseModel: StreamOneShotUser::class,
        )
        ->stream();

    iterator_to_array($stream->responses());
    expect(fn() => iterator_to_array($stream->responses()))
        ->toThrow(\RuntimeException::class, 'cannot be replayed');
});

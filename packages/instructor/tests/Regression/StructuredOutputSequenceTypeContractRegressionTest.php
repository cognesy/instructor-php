<?php declare(strict_types=1);

use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;

// Guards regression from instructor-v8iv (sequence() leaking get_class TypeError on scalar values).
it('throws explicit sequence contract error for non-sequenceable streamed values', function () {
    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [[
            new PartialInferenceResponse(contentDelta: '{"age":30}', finishReason: 'stop'),
        ]],
    );

    $stream = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
        ->with(
            messages: 'Extract user age',
            responseModel: Scalar::integer('age'),
        )
        ->stream();

    expect(fn() => iterator_to_array($stream->sequence(), false))
        ->toThrow(\RuntimeException::class, 'Expected Sequenceable value in sequence() stream, got int.');
});

<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

class PendingModeSwitchUser {
    public string $name;
    public int $age;
}

// Guards regression from instructor-zfmi (response()/stream() mode switch terminal failure).
it('reuses finalized response when stream is requested after non-stream response', function () {
    $driver = new FakeInferenceDriver(
        responses: [new InferenceResponse(content: '{"name":"Ann","age":30}')],
        streamBatches: [],
    );

    $pending = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
        ->with(
            messages: 'Extract user',
            responseModel: PendingModeSwitchUser::class,
        )
        ->create();

    $syncResponse = $pending->response();
    $streamResponse = $pending->stream()->finalResponse();
    $streamValue = $pending->stream()->finalValue();

    expect($driver->responseCalls)->toBe(1);
    expect($driver->streamCalls)->toBe(0);
    expect($streamResponse->content())->toBe($syncResponse->content());
    expect($streamValue)->toBeInstanceOf(PendingModeSwitchUser::class);
});

it('resolves final response after getIterator consumption for all cache policies', function (ResponseCachePolicy $policy) {
    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [[
            new PartialInferenceDelta(contentDelta: '{"name":"Ann"'),
            new PartialInferenceDelta(contentDelta: ',"age":30}', finishReason: 'stop'),
        ]],
    );

    $stream = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(
            driver: $driver,
            config: new StructuredOutputConfig(responseCachePolicy: $policy),
            outputMode: OutputMode::Json,
        ))
        ->with(
            messages: 'Extract user',
            responseModel: PendingModeSwitchUser::class,
        )
        ->stream();

    foreach ($stream->getIterator() as $_) {
        // consume raw emissions only
    }

    $final = $stream->finalResponse();
    $finalValue = $stream->finalValue();

    expect($driver->streamCalls)->toBe(1);
    expect($finalValue)->toBeInstanceOf(PendingModeSwitchUser::class);
    expect($final->content())->toContain('"age":30');
})->with([
    'no-cache' => [ResponseCachePolicy::None],
    'memory-cache' => [ResponseCachePolicy::Memory],
]);

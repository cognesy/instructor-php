<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
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
        ->withRuntime(makeStructuredRuntime(driver: $driver))
        ->with(
            messages: 'Extract user',
            responseModel: PendingModeSwitchUser::class,
            mode: OutputMode::Json,
        )
        ->create();

    $syncResponse = $pending->response();
    $streamResponse = $pending->stream()->finalResponse();

    expect($driver->responseCalls)->toBe(1);
    expect($driver->streamCalls)->toBe(0);
    expect($streamResponse->content())->toBe($syncResponse->content());
    expect($streamResponse->value())->toBeInstanceOf(PendingModeSwitchUser::class);
});

it('resolves final response after getIterator consumption for all cache policies', function (ResponseCachePolicy $policy) {
    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [[
            new PartialInferenceResponse(contentDelta: '{"name":"Ann"'),
            new PartialInferenceResponse(contentDelta: ',"age":30}', finishReason: 'stop'),
        ]],
    );

    $stream = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver))
        ->withConfig(new StructuredOutputConfig(responseCachePolicy: $policy))
        ->with(
            messages: 'Extract user',
            responseModel: PendingModeSwitchUser::class,
            mode: OutputMode::Json,
        )
        ->stream();

    foreach ($stream->getIterator() as $_) {
        // consume raw execution updates only
    }

    $final = $stream->finalResponse();

    expect($driver->streamCalls)->toBe(1);
    expect($final->value())->toBeInstanceOf(PendingModeSwitchUser::class);
    expect($final->content())->toContain('"age":30');
})->with([
    'no-cache' => [ResponseCachePolicy::None],
    'memory-cache' => [ResponseCachePolicy::Memory],
]);

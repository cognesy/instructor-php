<?php declare(strict_types=1);

use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

class PendingContractUser
{
    public string $name;
    public int $age;
}

it('is lazy until accessed and coordinates raw and structured reads through one execution', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '{"name":"Ava","age":34}'),
    ]);

    $pending = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
        ->with(
            messages: 'Extract user',
            responseModel: PendingContractUser::class,
        )
        ->create();

    expect($driver->responseCalls)->toBe(0);

    $raw = $pending->rawResponse();
    $response = $pending->response();
    $value = $pending->get();

    expect($driver->responseCalls)->toBe(1);
    expect($raw)->toBeInstanceOf(InferenceResponse::class);
    expect($response)->toBeInstanceOf(StructuredOutputResponse::class);
    expect($response->rawResponse())->toBe($raw);
    expect($value)->toBeInstanceOf(PendingContractUser::class);
    expect($value->name)->toBe('Ava');
    expect($value->age)->toBe(34);
});

it('reuses the finalized stream when rawResponse() is read after streaming', function () {
    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [[
            new PartialInferenceResponse(contentDelta: '{"name":"Lia"', usage: new \Cognesy\Polyglot\Inference\Data\Usage(outputTokens: 1)),
            new PartialInferenceResponse(contentDelta: ',"age":29}', finishReason: 'stop', usage: new \Cognesy\Polyglot\Inference\Data\Usage(outputTokens: 1)),
        ]],
    );

    $pending = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
        ->with(
            messages: 'Extract user',
            responseModel: PendingContractUser::class,
        )
        ->withStreaming()
        ->create();

    $finalFromStream = $pending->stream()->finalResponse();
    $raw = $pending->rawResponse();

    expect($driver->streamCalls)->toBe(1);
    expect($raw->content())->toBe($finalFromStream->content());
});

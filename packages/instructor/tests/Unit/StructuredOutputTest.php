<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Messages\ToolCall;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;


// Simple response class for deserialization
class TestUserStruct {
    public int $age;
    public string $name;
}

it('deserializes basic JSON into response class', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '{"name":"Jason","age":25}')
    ]);

    $user = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
        ->with(
            messages: 'Extract user',
            responseModel: TestUserStruct::class,
        )
        ->get();

    expect($user)->toBeInstanceOf(TestUserStruct::class);
    expect($user->name)->toBe('Jason');
    expect($user->age)->toBe(25);
});

it('uses tool call args in Tools mode when present', function () {
    $toolCalls = new ToolCalls(
        new ToolCall('extract', ['name' => 'Jane', 'age' => 22])
    );
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '', toolCalls: $toolCalls)
    ]);

    $user = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Tools))
        ->with(
            messages: 'Extract user',
            responseModel: TestUserStruct::class,
        )
        ->get();

    expect($user)->toBeInstanceOf(TestUserStruct::class);
    expect($user->name)->toBe('Jane');
    expect($user->age)->toBe(22);
});

it('caches processed response within the same PendingStructuredOutput', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '{"name":"A","age":1}')
    ]);

    $pending = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
        ->with(
            messages: 'Extract user',
            responseModel: TestUserStruct::class,
        )
        ->create();

    $first = $pending->get();
    $secondResponse = $pending->response();

    expect($driver->responseCalls)->toBe(1);
    expect($first)->toBeInstanceOf(TestUserStruct::class);
    expect($secondResponse)->toBeInstanceOf(StructuredOutputResponse::class);
    expect($secondResponse->value())->toBeInstanceOf(TestUserStruct::class);
    expect($secondResponse->inferenceResponse())->toBeInstanceOf(InferenceResponse::class);
});

it('returns stdClass when defaultToStdClass is enabled for JSON schema output', function () {
    $schema = [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
        ],
        'required' => ['name', 'age'],
    ];
    $toolCalls = new ToolCalls(
        new ToolCall('extract', ['name' => 'Jason', 'age' => 25])
    );
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '', toolCalls: $toolCalls)
    ]);

    $user = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(
            driver: $driver,
            outputMode: OutputMode::Tools,
            defaultToStdClass: true,
        ))
        ->with(
            messages: 'Extract user',
            responseModel: $schema,
        )
        ->get();

    expect($user)->toBeInstanceOf(stdClass::class);
    expect($user->name)->toBe('Jason');
    expect($user->age)->toBe(25);
});

it('supports runtime-style create with explicit request', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '{"name":"Mia","age":31}')
    ]);

    $pending = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
        ->create(new StructuredOutputRequest(
            messages: 'Extract user',
            requestedSchema: TestUserStruct::class,
        ));

    $user = $pending->get();

    expect($user)->toBeInstanceOf(TestUserStruct::class);
    expect($user->name)->toBe('Mia');
    expect($user->age)->toBe(31);
});

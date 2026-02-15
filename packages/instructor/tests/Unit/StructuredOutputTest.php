<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceRequestDriver;


// Simple response class for deserialization
class TestUserStruct {
    public int $age;
    public string $name;
}

it('deserializes basic JSON into response class', function () {
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: '{"name":"Jason","age":25}')
    ]);

    $user = (new StructuredOutput)
        ->withDriver($driver)
        ->with(
            messages: 'Extract user',
            responseModel: TestUserStruct::class,
            mode: OutputMode::Json,
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
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: '', toolCalls: $toolCalls)
    ]);

    $user = (new StructuredOutput)
        ->withDriver($driver)
        ->with(
            messages: 'Extract user',
            responseModel: TestUserStruct::class,
            mode: OutputMode::Tools,
        )
        ->get();

    expect($user)->toBeInstanceOf(TestUserStruct::class);
    expect($user->name)->toBe('Jane');
    expect($user->age)->toBe(22);
});

it('caches processed response within the same PendingStructuredOutput', function () {
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: '{"name":"A","age":1}')
    ]);

    $pending = (new StructuredOutput)
        ->withDriver($driver)
        ->with(
            messages: 'Extract user',
            responseModel: TestUserStruct::class,
            mode: OutputMode::Json,
        )
        ->create();

    $first = $pending->get();
    $secondResponse = $pending->response();

    expect($driver->responseCalls)->toBe(1);
    expect($first)->toBeInstanceOf(TestUserStruct::class);
    expect($secondResponse->value())->toBeInstanceOf(TestUserStruct::class);
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
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: '', toolCalls: $toolCalls)
    ]);

    $user = (new StructuredOutput)
        ->withDriver($driver)
        ->with(
            messages: 'Extract user',
            responseModel: $schema,
            mode: OutputMode::Tools,
        )
        ->withDefaultToStdClass()
        ->get();

    expect($user)->toBeInstanceOf(stdClass::class);
    expect($user->name)->toBe('Jason');
    expect($user->age)->toBe(25);
});

it('supports runtime-style create with explicit request', function () {
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: '{"name":"Mia","age":31}')
    ]);

    $pending = (new StructuredOutput)
        ->withDriver($driver)
        ->withOutputMode(OutputMode::Json)
        ->create(new StructuredOutputRequest(
            messages: 'Extract user',
            requestedSchema: TestUserStruct::class,
        ));

    $user = $pending->get();

    expect($user)->toBeInstanceOf(TestUserStruct::class);
    expect($user->name)->toBe('Mia');
    expect($user->age)->toBe(31);
});

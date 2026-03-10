<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\MockHttp;
use Cognesy\Instructor\Tests\Support\TestConfig;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicDriver;

class AnthropicTestUser
{
    public int $age;

    public string $name;
}

it('works with Anthropic mock responses in tools mode', function () {
    $json = '{"age":30,"name":"Alex"}';
    $client = MockHttp::get([$json], 'anthropic');
    $config = TestConfig::llmPreset('anthropic');
    $driver = new AnthropicDriver(
        config: $config,
        httpClient: $client,
        events: new EventDispatcher
    );

    $obj = (new StructuredOutput(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Tools)))
        ->withMessages([['role' => 'user', 'content' => 'test']])
        ->withResponseClass(AnthropicTestUser::class)
        ->getObject();

    expect($obj)->toBeInstanceOf(AnthropicTestUser::class);
    expect($obj->age)->toBe(30);
    expect($obj->name)->toBe('Alex');
});

it('works with Anthropic mock responses for scalars', function () {
    $intJson = '{"age":28}';
    $client = MockHttp::get([$intJson], 'anthropic');
    $config = TestConfig::llmPreset('anthropic');
    $driver = new AnthropicDriver(
        config: $config,
        httpClient: $client,
        events: new EventDispatcher
    );

    $v1 = (new StructuredOutput(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Tools)))
        ->with(
            messages: [['role' => 'user', 'content' => 'age?']],
            responseModel: Scalar::integer('age'),
        )
        ->get();

    expect($v1)->toBeInt()->toBe(28);
});

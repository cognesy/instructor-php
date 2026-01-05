<?php

use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\MockHttp;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicDriver;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Events\Dispatchers\EventDispatcher;

class AnthropicTestUser {
    public int $age;
    public string $name;
}

it('works with Anthropic mock responses in tools mode', function () {
    $json = '{"age":30,"name":"Alex"}';
    $client = MockHttp::get([$json], 'anthropic');

    $llmConfigArray = require __DIR__ . '/../Fixtures/Setup/config/llm.php';
    $preset = $llmConfigArray['presets']['anthropic'];

    // Filter to only include LLMConfig fields
    $configData = array_intersect_key($preset, [
        'apiUrl' => true,
        'apiKey' => true,
        'endpoint' => true,
        'metadata' => true,
        'model' => true,
        'maxTokens' => true,
        'contextLength' => true,
        'maxOutputLength' => true,
    ]);

    $config = LLMConfig::fromArray($configData);
    $driver = new AnthropicDriver(
        config: $config,
        httpClient: $client,
        events: new EventDispatcher()
    );

    $obj = (new StructuredOutput())
        ->withDriver($driver)
        ->withMessages([['role' => 'user', 'content' => 'test']])
        ->withResponseClass(AnthropicTestUser::class)
        ->withOutputMode(OutputMode::Tools)
        ->getObject();

    expect($obj)->toBeInstanceOf(AnthropicTestUser::class);
    expect($obj->age)->toBe(30);
    expect($obj->name)->toBe('Alex');
});

it('works with Anthropic mock responses for scalars', function () {
    $intJson = '{"age":28}';
    $client = MockHttp::get([$intJson], 'anthropic');

    $llmConfigArray = require __DIR__ . '/../Fixtures/Setup/config/llm.php';
    $preset = $llmConfigArray['presets']['anthropic'];

    // Filter to only include LLMConfig fields
    $configData = array_intersect_key($preset, [
        'apiUrl' => true,
        'apiKey' => true,
        'endpoint' => true,
        'metadata' => true,
        'model' => true,
        'maxTokens' => true,
        'contextLength' => true,
        'maxOutputLength' => true,
    ]);

    $config = LLMConfig::fromArray($configData);
    $driver = new AnthropicDriver(
        config: $config,
        httpClient: $client,
        events: new EventDispatcher()
    );

    $v1 = (new StructuredOutput())
        ->withDriver($driver)
        ->with(
            messages: [['role'=>'user','content'=>'age?']],
            responseModel: Scalar::integer('age'),
            mode: OutputMode::Tools,
        )
        ->get();

    expect($v1)->toBeInt()->toBe(28);
});

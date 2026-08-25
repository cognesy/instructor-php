<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\LLMProvider;

it('lists bundled preset names without resolving their environment templates', function () {
    $presets = LLMConfig::presetNames();

    expect($presets)->toContain('deepseek', 'qwen')
        ->and($presets)->toBe(array_values(array_unique($presets)));
});

it('creates driver from explicit config and resolves correct class', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'gpt-4o-mini',
        driver: 'openai',
    );

    $provider = LLMProvider::new()->withLLMConfig($config);
    $httpClient = (new HttpClientBuilder())->create();
    $driver = BundledInferenceDrivers::registry()->makeDriver(
        'openai',
        $provider->resolveConfig(),
        $httpClient,
        new EventDispatcher(),
    );

    // Resolved via the bundled inference drivers. Since instructor-eexl.9 'openai' is an
    // InferenceDriverSpec rather than a class of its own, so the class name no longer carries
    // the provider's identity -- the resolved config does, and that is what this test is about.
    expect($driver)->toBeInstanceOf(CanProcessInferenceRequest::class)
        ->and($provider->resolveConfig()->driver)->toBe('openai');
});

it('preserves model override when config overrides are applied later', function () {
    $config = new LLMConfig(
        driver: 'openai',
        model: 'base-model',
        maxTokens: 256,
    );

    $resolved = LLMProvider::new($config)
        ->withModel('model-from-with-model')
        ->withConfigOverrides(['maxTokens' => 2048])
        ->resolveConfig();

    expect($resolved->model)->toBe('model-from-with-model');
    expect($resolved->maxTokens)->toBe(2048);
});

it('preserves both config overrides and model regardless of call order', function () {
    $config = new LLMConfig(
        driver: 'openai',
        model: 'base-model',
        maxTokens: 256,
    );

    $resolved = LLMProvider::new($config)
        ->withConfigOverrides(['maxTokens' => 1024])
        ->withModel('model-from-with-model')
        ->resolveConfig();

    expect($resolved->model)->toBe('model-from-with-model');
    expect($resolved->maxTokens)->toBe(1024);
});

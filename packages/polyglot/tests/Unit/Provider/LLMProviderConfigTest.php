<?php

use Cognesy\Config\Providers\ArrayConfigProvider;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Creation\InferenceDriverFactory;
use Cognesy\Polyglot\Inference\LLMProvider;

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
    $factory = new InferenceDriverFactory(new EventDispatcher());
    $driver = $factory->makeDriver($provider->resolveConfig(), $httpClient);

    // Should be OpenAI driver resolved via InferenceDriverFactory bundledDrivers
    expect(get_class($driver))->toContain('OpenAIDriver');
});

it('preserves model override when config overrides are applied later', function () {
    $config = new ArrayConfigProvider([
        'llm' => [
            'defaultPreset' => 'default',
            'presets' => [
                'default' => [
                    'driver' => 'openai',
                    'model' => 'base-model',
                    'maxTokens' => 256,
                ],
            ],
        ],
    ]);

    $resolved = LLMProvider::new($config)
        ->withModel('model-from-with-model')
        ->withConfigOverrides(['maxTokens' => 2048])
        ->resolveConfig();

    expect($resolved->model)->toBe('model-from-with-model');
    expect($resolved->maxTokens)->toBe(2048);
});

it('preserves both config overrides and model regardless of call order', function () {
    $config = new ArrayConfigProvider([
        'llm' => [
            'defaultPreset' => 'default',
            'presets' => [
                'default' => [
                    'driver' => 'openai',
                    'model' => 'base-model',
                    'maxTokens' => 256,
                ],
            ],
        ],
    ]);

    $resolved = LLMProvider::new($config)
        ->withConfigOverrides(['maxTokens' => 1024])
        ->withModel('model-from-with-model')
        ->resolveConfig();

    expect($resolved->model)->toBe('model-from-with-model');
    expect($resolved->maxTokens)->toBe(1024);
});

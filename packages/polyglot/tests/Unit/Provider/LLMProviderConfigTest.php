<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\HttpClientBuilder;
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

    $provider = LLMProvider::new()->withConfig($config);
    $httpClient = (new HttpClientBuilder())->create();
    $factory = new InferenceDriverFactory(new EventDispatcher());
    $driver = $factory->makeDriver($provider->resolveConfig(), $httpClient);

    // Should be OpenAI driver resolved via InferenceDriverFactory bundledDrivers
    expect(get_class($driver))->toContain('OpenAIDriver');
});

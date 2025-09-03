<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;
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
    $driver = $provider->createDriver();

    // Should be OpenAI driver resolved via InferenceDriverFactory bundledDrivers
    expect(get_class($driver))->toContain('OpenAIDriver');
});


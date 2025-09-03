<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

it('maps InferenceRequest to Anthropic HttpRequest correctly', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.anthropic.com/v1',
        apiKey: 'KEY',
        endpoint: '/messages',
        metadata: ['apiVersion' => '2023-06-01'],
        model: 'claude-3-haiku-20240307',
        driver: 'anthropic'
    );
    $adapter = new AnthropicRequestAdapter(
        $config,
        new AnthropicBodyFormat($config, new OpenAIMessageFormat())
    );

    $req = new InferenceRequest(messages: 'Hello', options: ['stream' => true]);
    $http = $adapter->toHttpRequest($req);

    expect($http->method())->toBe('POST');
    expect($http->url())->toBe('https://api.anthropic.com/v1/messages');
    $headers = array_change_key_case($http->headers(), CASE_LOWER);
    expect($headers['x-api-key'] ?? null)->toBe('KEY');
    expect($headers['anthropic-version'] ?? null)->toBe('2023-06-01');
    expect($http->isStreamed())->toBeTrue();
});


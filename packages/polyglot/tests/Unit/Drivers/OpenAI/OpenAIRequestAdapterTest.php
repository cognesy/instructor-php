<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;

it('maps InferenceRequest to OpenAI HttpRequest correctly (non-streaming)', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'gpt-4o-mini',
        driver: 'openai'
    );
    $adapter = new OpenAIRequestAdapter(
        $config,
        new OpenAIBodyFormat($config, new OpenAIMessageFormat())
    );

    $req = new InferenceRequest(
        messages: 'Hello',
        model: '',
        options: ['stream' => false]
    );

    $http = $adapter->toHttpRequest($req);

    expect($http->method())->toBe('POST');
    expect($http->url())->toBe('https://api.openai.com/v1/chat/completions');
    $headers = array_change_key_case($http->headers(), CASE_LOWER);
    expect($headers['authorization'] ?? null)->toBe('Bearer KEY');
    expect($headers['content-type'] ?? null)->toContain('application/json');
    expect($http->isStreamed())->toBeFalse();
    $body = json_decode($http->body()->toString(), true);
    expect($body['model'])->toBe('gpt-4o-mini');
    expect($body['messages'][0]['role'])->toBe('user');
    expect($body['messages'][0]['content'])->toBe('Hello');
});


<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

it('OpenAI: response_format.type is json_object in JSON modes and max_tokens maps to max_completion_tokens', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'gpt-4o-mini',
        driver: 'openai',
    );

    $body = new OpenAIBodyFormat($config, new OpenAIMessageFormat());

    $req = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'gpt-4o-mini',
        options: ['max_tokens' => 123, 'stream' => true],
        responseFormat: ResponseFormat::jsonObject(),
    );

    $json = $body->toRequestBody($req);

    expect(($json['response_format']['type'] ?? ''))->toBe('json_object');
    expect(isset($json['max_tokens']))->toBeFalse();
    expect($json['max_completion_tokens'] ?? null)->toBe(123);
    expect($json['stream_options']['include_usage'] ?? null)->toBeTrue();
});

it('OpenAI: derives response format from ResponseFormat when mode is unrestricted', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'gpt-4o-mini',
        driver: 'openai',
    );

    $body = new OpenAIBodyFormat($config, new OpenAIMessageFormat());

    $req = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'gpt-4o-mini',
        responseFormat: ResponseFormat::jsonSchema(
            schema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            name: 'user',
            strict: false,
        ),
    );

    $json = $body->toRequestBody($req);

    expect($json['response_format'] ?? [])->toBe([
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'user',
            'schema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            'strict' => false,
        ],
    ]);
});

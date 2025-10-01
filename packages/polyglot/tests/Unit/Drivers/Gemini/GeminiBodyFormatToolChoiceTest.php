<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiMessageFormat;

it('Gemini native: tool_config.allowed_function_names is an array of names', function () {
    $config = new LLMConfig(
        apiUrl: 'https://example.googleapis.com/v1beta',
        apiKey: 'KEY',
        endpoint: '/models/{model}:generateContent',
        model: 'gemini-1.5-flash',
        driver: 'gemini',
    );

    $body = new GeminiBodyFormat($config, new GeminiMessageFormat());

    $tools = [[
        'type' => 'function',
        'function' => [
            'name' => 'search',
            'parameters' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']], 'required' => ['q']]
        ]
    ]];

    $req = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'gemini-1.5-flash',
        tools: $tools,
        toolChoice: [ 'function' => ['name' => 'search'] ],
    );

    $json = $body->toRequestBody($req);

    $allowed = $json['tool_config']['function_calling_config']['allowed_function_names'] ?? null;
    expect($allowed)->toBeArray();
    expect($allowed)->toContain('search');
});

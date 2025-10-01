<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

it('OpenAI: tool_choice maps to function selection when name provided', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'gpt-4o-mini',
        driver: 'openai',
    );

    $body = new OpenAIBodyFormat($config, new OpenAIMessageFormat());

    $tools = [[
        'type' => 'function',
        'function' => [
            'name' => 'search',
            'parameters' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']], 'required' => ['q']]
        ]
    ]];

    $req = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'gpt-4o-mini',
        tools: $tools,
        toolChoice: [ 'function' => ['name' => 'search'] ],
        options: ['stream' => false],
    );

    $json = $body->toRequestBody($req);

    expect(($json['tool_choice']['type'] ?? ''))->toBe('function');
    expect(($json['tool_choice']['function']['name'] ?? ''))->toBe('search');
});


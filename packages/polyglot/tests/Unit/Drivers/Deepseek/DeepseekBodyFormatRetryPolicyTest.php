<?php declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\Deepseek\DeepseekBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

it('Deepseek: omits retryPolicy from request body', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.deepseek.com',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'deepseek-chat',
        driver: 'deepseek',
    );

    $body = new DeepseekBodyFormat($config, new OpenAIMessageFormat());

    $req = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'deepseek-chat',
        retryPolicy: new InferenceRetryPolicy(maxAttempts: 2),
    );

    $json = $body->toRequestBody($req);

    expect($json)->not->toHaveKey('retryPolicy');
    expect($json)->not->toHaveKey('retry_policy');
});

it('Deepseek: omits response_format when tools are present', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.deepseek.com',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'deepseek-chat',
        driver: 'deepseek',
    );

    $body = new DeepseekBodyFormat($config, new OpenAIMessageFormat());

    $req = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'deepseek-chat',
        tools: [[
            'type' => 'function',
            'function' => [
                'name' => 'extract_data',
                'description' => 'Extract data',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['name' => ['type' => 'string']],
                    'required' => ['name'],
                ],
            ],
        ]],
        toolChoice: ['type' => 'function', 'function' => ['name' => 'extract_data']],
        responseFormat: new ResponseFormat(
            'json_schema',
            [
                'type' => 'object',
                'properties' => ['name' => ['type' => 'string']],
                'required' => ['name'],
            ],
            'ExtractedData',
        ),
    );

    $json = $body->toRequestBody($req);

    expect($json)->toHaveKey('tools')
        ->toHaveKey('tool_choice')
        ->not->toHaveKey('response_format');
});

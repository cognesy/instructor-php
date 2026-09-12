<?php declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Drivers\Deepseek\DeepseekBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

it('DeepSeek V4: omits retryPolicy from request body', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.deepseek.com',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'deepseek-v4-flash',
        driver: 'deepseek',
    );

    $body = new DeepseekBodyFormat($config, new OpenAIMessageFormat());

    $req = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'deepseek-v4-flash',
        retryPolicy: new InferenceRetryPolicy(maxAttempts: 2),
    );

    $json = $body->toRequestBody($req);

    expect($json)->not->toHaveKey('retryPolicy');
    expect($json)->not->toHaveKey('retry_policy');
});

it('DeepSeek V4: forwards thinking and reasoning_effort options', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.deepseek.com',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'deepseek-v4-pro',
        driver: 'deepseek',
    );

    $body = new DeepseekBodyFormat($config, new OpenAIMessageFormat());

    $json = $body->toRequestBody(new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Return V4_OK.']]),
        model: 'deepseek-v4-pro',
        options: [
            'thinking' => ['type' => 'enabled'],
            'reasoning_effort' => 'low',
        ],
    ));

    expect($json['model'])->toBe('deepseek-v4-pro')
        ->and($json['thinking'])->toBe(['type' => 'enabled'])
        ->and($json['reasoning_effort'])->toBe('low');
});

it('DeepSeek V4: preserves response format and specific tool choice when tools are present', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.deepseek.com',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'deepseek-v4-flash',
        driver: 'deepseek',
    );

    $body = new DeepseekBodyFormat($config, new OpenAIMessageFormat());

    $req = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'deepseek-v4-flash',
        tools: ToolDefinitions::fromArray([[
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
        ]]),
        toolChoice: ToolChoice::specific('extract_data'),
        responseFormat: ResponseFormat::jsonObject(),
    );

    $json = $body->toRequestBody($req);

    expect($json)->toHaveKey('tools')
        ->toHaveKey('tool_choice', [
            'type' => 'function',
            'function' => ['name' => 'extract_data'],
        ])
        ->toHaveKey('response_format', ['type' => 'json_object']);
});

it('DeepSeek V4: rejects JSON Schema instead of silently weakening it', function () {
    $body = new DeepseekBodyFormat(
        new LLMConfig(model: 'deepseek-v4-flash', driver: 'deepseek'),
        new OpenAIMessageFormat(),
    );
    $request = new InferenceRequest(
        model: 'deepseek-v4-flash',
        responseFormat: ResponseFormat::jsonSchema(['type' => 'object']),
    );

    expect(fn () => $body->toRequestBody($request))
        ->toThrow(InvalidArgumentException::class, 'cannot render JSON Schema');
});

it('DeepSeek V4: preserves a specific tool choice when thinking is disabled', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.deepseek.com',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'deepseek-v4-flash',
        driver: 'deepseek',
    );

    $body = new DeepseekBodyFormat($config, new OpenAIMessageFormat());
    $request = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'deepseek-v4-flash',
        tools: ToolDefinitions::fromArray([[
            'type' => 'function',
            'function' => [
                'name' => 'extract_data',
                'description' => 'Extract data',
                'parameters' => ['type' => 'object', 'properties' => []],
            ],
        ]]),
        toolChoice: ToolChoice::specific('extract_data'),
        options: ['thinking' => ['type' => 'disabled']],
    );

    expect($body->toRequestBody($request)['tool_choice'])->toBe([
        'type' => 'function',
        'function' => ['name' => 'extract_data'],
    ]);
});

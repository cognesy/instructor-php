<?php declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\Qwen\QwenBodyFormat;

it('Qwen: maps thinking option to enable_thinking', function () {
    $config = new LLMConfig(
        apiUrl: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'qwen3.8-max',
        driver: 'qwen',
    );

    $body = new QwenBodyFormat($config, new OpenAIMessageFormat());
    $request = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'qwen3.8-max',
        options: [
            'thinking' => 'enabled',
            'reasoning_effort' => 'low',
        ],
    );

    $json = $body->toRequestBody($request);

    expect($json)->toHaveKey('enable_thinking', true);
    expect($json)->toHaveKey('reasoning_effort', 'low');
    expect($json)->not->toHaveKey('thinking');
});

it('Qwen3.8-Max: preserves native JSON Schema response format', function () {
    $config = new LLMConfig(
        apiUrl: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'qwen3.8-max',
        driver: 'qwen',
    );

    $body = new QwenBodyFormat($config, new OpenAIMessageFormat());
    $request = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Return JSON.']]),
        model: 'qwen3.8-max',
        responseFormat: ResponseFormat::jsonSchema(
            schema: ['type' => 'object', 'properties' => ['answer' => ['type' => 'string']]],
            name: 'answer',
            strict: true,
        ),
    );

    expect($body->toRequestBody($request)['response_format'])->toBe([
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'answer',
            'schema' => [
                'type' => 'object',
                'properties' => ['answer' => ['type' => 'string']],
            ],
            'strict' => true,
        ],
    ]);
});

it('older Qwen models: degrades JSON Schema to JSON Object', function () {
    $config = new LLMConfig(
        apiUrl: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'qwen3-max-preview',
        driver: 'qwen',
    );

    $body = new QwenBodyFormat($config, new OpenAIMessageFormat());
    $request = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Return JSON.']]),
        model: 'qwen3-max-preview',
        responseFormat: ResponseFormat::jsonSchema(
            schema: ['type' => 'object', 'properties' => ['answer' => ['type' => 'string']]],
            name: 'answer',
            strict: true,
        ),
    );

    expect($body->toRequestBody($request)['response_format'])->toBe(['type' => 'json_object']);
});

it('Qwen: downgrades unsupported required tool choice to auto', function () {
    $config = new LLMConfig(
        apiUrl: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'qwen3.8-max',
        driver: 'qwen',
    );

    $body = new QwenBodyFormat($config, new OpenAIMessageFormat());
    $request = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Use the tool.']]),
        model: 'qwen3.8-max',
        tools: ToolDefinitions::fromArray([[
            'type' => 'function',
            'function' => [
                'name' => 'lookup',
                'description' => 'Look up a value',
                'parameters' => ['type' => 'object', 'properties' => []],
            ],
        ]]),
        toolChoice: ToolChoice::required(),
    );

    expect($body->toRequestBody($request)['tool_choice'])->toBe('auto');
});

it('Qwen: downgrades specific tool choice while thinking is enabled', function () {
    $config = new LLMConfig(
        apiUrl: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'qwen3.8-max',
        driver: 'qwen',
    );

    $body = new QwenBodyFormat($config, new OpenAIMessageFormat());
    $request = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Use the tool.']]),
        model: 'qwen3.8-max',
        options: ['enable_thinking' => true],
        tools: ToolDefinitions::fromArray([[
            'type' => 'function',
            'function' => [
                'name' => 'lookup',
                'description' => 'Look up a value',
                'parameters' => ['type' => 'object', 'properties' => []],
            ],
        ]]),
        toolChoice: ToolChoice::specific('lookup'),
    );

    expect($body->toRequestBody($request)['tool_choice'])->toBe('auto');
});

it('Qwen: preserves explicit enable_thinking as-is', function () {
    $config = new LLMConfig(
        apiUrl: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'qwen3.8-max',
        driver: 'qwen',
    );

    $body = new QwenBodyFormat($config, new OpenAIMessageFormat());
    $request = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'qwen3.8-max',
        options: ['thinking' => false, 'enable_thinking' => true],
    );

    $json = $body->toRequestBody($request);

    expect($json)->toHaveKey('enable_thinking', true);
    expect($json)->not->toHaveKey('thinking');
});

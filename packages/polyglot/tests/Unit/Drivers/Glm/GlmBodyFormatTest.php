<?php declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Drivers\Glm\GlmBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

it('GLM: maps enable_thinking to thinking', function () {
    $config = new LLMConfig(
        apiUrl: 'https://open.bigmodel.cn/api/paas/v4',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'glm-4.5',
        driver: 'glm',
    );

    $body = new GlmBodyFormat($config, new OpenAIMessageFormat());
    $request = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'glm-4.5',
        options: ['enable_thinking' => 'true'],
    );

    $json = $body->toRequestBody($request);

    expect($json)->toHaveKey('thinking', true);
    expect($json)->not->toHaveKey('enable_thinking');
});

it('GLM: sets tool_stream=true by default for streaming tool calls', function () {
    $config = new LLMConfig(
        apiUrl: 'https://open.bigmodel.cn/api/paas/v4',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'glm-4.5',
        driver: 'glm',
    );

    $body = new GlmBodyFormat($config, new OpenAIMessageFormat());
    $request = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'glm-4.5',
        tools: ToolDefinitions::fromArray([[
            'type' => 'function',
            'function' => [
                'name' => 'search',
                'description' => 'Lookup information',
                'parameters' => ['type' => 'object', 'properties' => []],
            ],
        ]]),
        options: ['stream' => true],
    );

    $json = $body->toRequestBody($request);

    expect($json)->toHaveKey('tool_stream', true);
});

it('GLM: keeps explicit tool_stream value', function () {
    $config = new LLMConfig(
        apiUrl: 'https://open.bigmodel.cn/api/paas/v4',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'glm-4.5',
        driver: 'glm',
    );

    $body = new GlmBodyFormat($config, new OpenAIMessageFormat());
    $request = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'glm-4.5',
        tools: ToolDefinitions::fromArray([[
            'type' => 'function',
            'function' => [
                'name' => 'search',
                'description' => 'Lookup information',
                'parameters' => ['type' => 'object', 'properties' => []],
            ],
        ]]),
        options: ['stream' => true, 'tool_stream' => false],
    );

    $json = $body->toRequestBody($request);

    expect($json)->toHaveKey('tool_stream', false);
});

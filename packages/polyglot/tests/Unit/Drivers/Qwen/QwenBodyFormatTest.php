<?php declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Core\InferenceRequestPreflight;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\Qwen\QwenBodyFormat;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;

function qwenPackagedModelCatalog(): ModelCatalog
{
    return ModelCatalog::fromPaths(dirname(__DIR__, 4) . '/resources/config/llm/models');
}

it('Qwen: maps thinking option to enable_thinking', function () {
    $config = new LLMConfig(
        apiUrl: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'qwen3.8-max',
        driver: 'qwen',
    );

    $body = new QwenBodyFormat($config, new OpenAIMessageFormat());
    $request = (new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'qwen3.8-max',
        options: [
            'thinking' => 'enabled',
            'reasoning_effort' => 'low',
        ],
    ))->withModelProfile(qwenPackagedModelCatalog()->find('qwen', 'qwen3.8-max'));

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
        model: 'qwen3-max-preview',
        driver: 'qwen',
    );

    $body = new QwenBodyFormat($config, new OpenAIMessageFormat());
    $request = (new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Return JSON.']]),
        model: 'qwen3.8-max',
        responseFormat: ResponseFormat::jsonSchema(
            schema: ['type' => 'object', 'properties' => ['answer' => ['type' => 'string']]],
            name: 'answer',
            strict: true,
        ),
    ))->withModelProfile(qwenPackagedModelCatalog()->find('qwen', 'qwen3.8-max'));

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
        model: 'qwen3.8-max',
        driver: 'qwen',
    );

    $body = new QwenBodyFormat($config, new OpenAIMessageFormat());
    $request = (new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Return JSON.']]),
        model: 'qwen3-max-preview',
        responseFormat: ResponseFormat::jsonSchema(
            schema: ['type' => 'object', 'properties' => ['answer' => ['type' => 'string']]],
            name: 'answer',
            strict: true,
        ),
    ))->withModelProfile(qwenPackagedModelCatalog()->find('qwen', 'qwen3-max-preview'));
    $request = (new InferenceRequestPreflight(allowLossyFallback: true))->apply($request);

    expect($body->toRequestBody($request)['response_format'])->toBe(['type' => 'json_object']);
});

it('Qwen: rejects unsupported required tool choice instead of downgrading it', function () {
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

    expect(fn () => $body->toRequestBody($request))
        ->toThrow(InvalidArgumentException::class, 'cannot render required tool choice');
});

it('Qwen: rejects a specific tool choice while thinking is enabled instead of downgrading it', function () {
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

    expect(fn () => $body->toRequestBody($request))
        ->toThrow(InvalidArgumentException::class, 'specific tool choice while thinking is enabled');
});

it('Qwen: fails explicitly when local policy and protocol renderability disagree', function () {
    $config = new LLMConfig(
        apiUrl: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'custom-qwen',
        driver: 'qwen',
    );
    $profile = ModelCatalog::fromArray([
        'version' => 'test-v1',
        'models' => [[
            'driver' => 'qwen',
            'model' => 'custom-qwen',
            'capabilities' => [
                'tools' => 'supported',
                'jsonObject' => 'supported',
                'responseFormatWithTools' => 'supported',
            ],
        ]],
    ])->find('qwen', 'custom-qwen');
    $request = (new InferenceRequest(
        model: 'custom-qwen',
        tools: ToolDefinitions::fromArray([[
            'type' => 'function',
            'function' => [
                'name' => 'lookup',
                'description' => 'Look up a value',
                'parameters' => ['type' => 'object', 'properties' => []],
            ],
        ]]),
        responseFormat: ResponseFormat::jsonObject(),
    ))->withModelProfile($profile);

    expect((new InferenceRequestPreflight())->apply($request))->toBe($request)
        ->and(fn () => (new QwenBodyFormat($config, new OpenAIMessageFormat()))->toRequestBody($request))
        ->toThrow(InvalidArgumentException::class, 'non-text response format together with tools');
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

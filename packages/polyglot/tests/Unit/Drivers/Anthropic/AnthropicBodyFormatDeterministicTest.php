<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

it('Anthropic: system + user mapping and cache_control on cached system', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.anthropic.com',
        apiKey: 'KEY',
        endpoint: '/v1/messages',
        model: 'claude-3-sonnet',
        driver: 'anthropic',
    );

    $body = new AnthropicBodyFormat($config, new OpenAIMessageFormat());

    $cached = new CachedInferenceContext(
        messages: [ ['role' => 'system', 'content' => 'Cached system.'] ],
    );

    $req = new InferenceRequest(
        messages: Messages::fromAny([
            ['role' => 'system', 'content' => 'Live system.'],
            ['role' => 'user', 'content' => 'Hi'],
        ]),
        model: 'claude-3-sonnet',
        cachedContext: $cached,
        options: ['parallel_tool_calls' => true],
    );

    $json = $body->toRequestBody($req);

    // system entries come from cached + live system parts
    expect($json['system'] ?? null)->toBeArray();
    $sys = $json['system'];
    expect($sys[0]['type'] ?? '')->toBe('text');
    expect(isset($sys[0]['cache_control']))->toBeTrue();
    expect($sys[1]['type'] ?? '')->toBe('text');

    // messages excludes system role and has user
    expect($json['messages'] ?? null)->toBeArray();
    expect($json['messages'][0]['role'] ?? '')->not->toBe('system');
});

it('Anthropic: tool_choice includes disable_parallel_tool_use flag', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.anthropic.com',
        apiKey: 'KEY',
        endpoint: '/v1/messages',
        model: 'claude-3-haiku',
        driver: 'anthropic',
        options: [],
    );

    $body = new AnthropicBodyFormat($config, new OpenAIMessageFormat());

    $tools = [[
        'type' => 'function',
        'function' => [ 'name' => 'search', 'parameters' => ['type' => 'object'] ],
    ]];

    $req = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'claude-3-haiku',
        tools: $tools,
        toolChoice: 'auto',
        options: ['parallel_tool_calls' => false],
    );

    $json = $body->toRequestBody($req);
    expect($json['tool_choice']['disable_parallel_tool_use'] ?? null)->toBeTrue();
});

it('Anthropic: cache_control applied to last cached message', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.anthropic.com',
        apiKey: 'KEY',
        endpoint: '/v1/messages',
        model: 'claude-3-sonnet',
        driver: 'anthropic',
    );

    $body = new AnthropicBodyFormat($config, new AnthropicMessageFormat());

    $cached = new CachedInferenceContext(
        messages: [
            ['role' => 'user', 'content' => 'Cached one.'],
            ['role' => 'assistant', 'content' => 'Cached two.'],
        ],
    );

    $req = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Live message.']]),
        model: 'claude-3-sonnet',
        cachedContext: $cached,
    );

    $json = $body->toRequestBody($req);
    $messages = $json['messages'] ?? [];

    expect(is_array($messages[0]['content'] ?? null))->toBeFalse();
    expect(is_array($messages[1]['content'] ?? null))->toBeTrue();
    expect($messages[1]['content'][0]['cache_control']['type'] ?? null)->toBe('ephemeral');
    expect(is_array($messages[2]['content'] ?? null))->toBeFalse();
});

it('Anthropic: toRequestBody does not leak parallel tool setting between calls', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.anthropic.com',
        apiKey: 'KEY',
        endpoint: '/v1/messages',
        model: 'claude-3-haiku',
        driver: 'anthropic',
    );

    $body = new AnthropicBodyFormat($config, new OpenAIMessageFormat());

    $tools = [[
        'type' => 'function',
        'function' => [ 'name' => 'search', 'parameters' => ['type' => 'object'] ],
    ]];

    $first = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'First']]),
        model: 'claude-3-haiku',
        tools: $tools,
        toolChoice: 'auto',
        options: ['parallel_tool_calls' => true],
    );

    $second = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Second']]),
        model: 'claude-3-haiku',
        tools: $tools,
        toolChoice: 'auto',
        options: [],
    );

    $firstJson = $body->toRequestBody($first);
    $secondJson = $body->toRequestBody($second);

    expect($firstJson['tool_choice']['disable_parallel_tool_use'] ?? null)->toBeFalse();
    expect($secondJson['tool_choice']['disable_parallel_tool_use'] ?? null)->toBeTrue();
});

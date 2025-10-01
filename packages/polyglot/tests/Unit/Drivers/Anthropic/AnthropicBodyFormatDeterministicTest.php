<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\CachedContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicBodyFormat;
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

    $cached = new CachedContext(
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


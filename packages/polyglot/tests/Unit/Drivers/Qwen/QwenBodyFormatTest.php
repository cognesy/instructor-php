<?php declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\Qwen\QwenBodyFormat;

it('Qwen: maps thinking option to enable_thinking', function () {
    $config = new LLMConfig(
        apiUrl: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'qwen3-max-preview',
        driver: 'qwen',
    );

    $body = new QwenBodyFormat($config, new OpenAIMessageFormat());
    $request = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'qwen3-max-preview',
        options: ['thinking' => 'enabled'],
    );

    $json = $body->toRequestBody($request);

    expect($json)->toHaveKey('enable_thinking', true);
    expect($json)->not->toHaveKey('thinking');
});

it('Qwen: preserves explicit enable_thinking as-is', function () {
    $config = new LLMConfig(
        apiUrl: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'qwen3-max-preview',
        driver: 'qwen',
    );

    $body = new QwenBodyFormat($config, new OpenAIMessageFormat());
    $request = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'qwen3-max-preview',
        options: ['thinking' => false, 'enable_thinking' => true],
    );

    $json = $body->toRequestBody($request);

    expect($json)->toHaveKey('enable_thinking', true);
    expect($json)->not->toHaveKey('thinking');
});

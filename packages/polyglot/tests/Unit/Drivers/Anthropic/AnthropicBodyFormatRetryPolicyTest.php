<?php declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

it('Anthropic: omits retryPolicy from request body', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.anthropic.com',
        apiKey: 'KEY',
        endpoint: '/v1/messages',
        model: 'claude-3-sonnet',
        driver: 'anthropic',
    );

    $body = new AnthropicBodyFormat($config, new OpenAIMessageFormat());

    $req = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'claude-3-sonnet',
        retryPolicy: new InferenceRetryPolicy(maxAttempts: 3),
    );

    $json = $body->toRequestBody($req);

    expect($json)->not->toHaveKey('retryPolicy');
    expect($json)->not->toHaveKey('retry_policy');
});

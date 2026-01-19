<?php declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiMessageFormat;

it('Gemini native: omits retryPolicy from request body', function () {
    $config = new LLMConfig(
        apiUrl: 'https://example.googleapis.com/v1beta',
        apiKey: 'KEY',
        endpoint: '/models/{model}:generateContent',
        model: 'gemini-1.5-flash',
        driver: 'gemini',
    );

    $body = new GeminiBodyFormat($config, new GeminiMessageFormat());

    $req = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'gemini-1.5-flash',
        retryPolicy: new InferenceRetryPolicy(maxAttempts: 2),
    );

    $json = $body->toRequestBody($req);
    $generationConfig = $json['generationConfig'] ?? [];

    expect($json)->not->toHaveKey('retryPolicy');
    expect($json)->not->toHaveKey('retry_policy');
    expect($generationConfig)->not->toHaveKey('retryPolicy');
    expect($generationConfig)->not->toHaveKey('retry_policy');
});

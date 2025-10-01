<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiMessageFormat;

it('Gemini native: systemInstruction uses array of parts with text', function () {
    $config = new LLMConfig(
        apiUrl: 'https://example.googleapis.com/v1beta',
        apiKey: 'KEY',
        endpoint: '/models/{model}:generateContent',
        model: 'gemini-1.5-flash',
        driver: 'gemini',
    );

    $body = new GeminiBodyFormat($config, new GeminiMessageFormat());

    $req = new InferenceRequest(
        messages: Messages::fromAny([
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => 'Hi'],
        ]),
        model: 'gemini-1.5-flash'
    );

    $json = $body->toRequestBody($req);

    expect($json['systemInstruction'] ?? null)->not->toBeEmpty();
    expect($json['systemInstruction']['parts'] ?? null)->toBeArray();
    $text = $json['systemInstruction']['parts'][0]['text'] ?? null;
    expect(trim((string)$text))->toBe('You are helpful.');
});

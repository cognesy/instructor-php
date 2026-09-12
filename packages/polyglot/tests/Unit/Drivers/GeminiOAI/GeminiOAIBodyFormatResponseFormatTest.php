<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\GeminiOAI\GeminiOAIBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

it('Gemini-OAI: renders JSON Object and rejects JSON Schema without explicit preparation', function () {
    $config = new LLMConfig(
        apiUrl: 'https://example.googleapis.com/v1beta',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'gemini-1.5-flash',
        driver: 'gemini-oai',
    );

    $body = new GeminiOAIBodyFormat($config, new OpenAIMessageFormat());

    $request = fn (ResponseFormat $format): InferenceRequest => new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'gemini-1.5-flash',
        options: ['stream' => false],
        responseFormat: $format,
    );

    expect($body->toRequestBody($request(ResponseFormat::jsonObject())))
        ->toHaveKey('response_format.type', 'json_object')
        ->and(fn () => $body->toRequestBody($request(
            ResponseFormat::jsonSchema(schema: ['type' => 'object']),
        )))
        ->toThrow(InvalidArgumentException::class, 'cannot render JSON Schema');
});

<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\GeminiOAI\GeminiOAIBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

it('Gemini-OAI: response_format.type is json_object for json object and json schema response formats', function () {
    $config = new LLMConfig(
        apiUrl: 'https://example.googleapis.com/v1beta',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'gemini-1.5-flash',
        driver: 'gemini-oai',
    );

    $body = new GeminiOAIBodyFormat($config, new OpenAIMessageFormat());

    foreach ([ResponseFormat::jsonObject(), ResponseFormat::jsonSchema(schema: ['type' => 'object'])] as $responseFormat) {
        $req = new InferenceRequest(
            messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
            model: 'gemini-1.5-flash',
            options: ['stream' => false],
            responseFormat: $responseFormat,
        );
        $json = $body->toRequestBody($req);
        expect(($json['response_format']['type'] ?? ''))->toBe('json_object');
    }
});

<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2BodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

it('CohereV2: json_object schema has required fields for every object with properties', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.cohere.ai',
        apiKey: 'KEY',
        endpoint: '/v2/chat',
        model: 'command-r-plus-08-2024',
        driver: 'cohere',
    );

    $body = new CohereV2BodyFormat($config, new OpenAIMessageFormat());

    $responseFormat = ResponseFormat::jsonSchema(
        schema: [
            'type' => 'object',
            'properties' => [
                'list' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'date' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
            ],
        ],
    );

    $req = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        responseFormat: $responseFormat,
    );

    $json = $body->toRequestBody($req);
    $schema = $json['response_format']['schema'];

    expect($json['response_format']['type'])->toBe('json_object');
    expect($schema['required'] ?? [])->toBe(['list']);
    expect($schema['properties']['list']['required'] ?? [])->toBe(['title', 'status', 'date']);
    expect($schema['properties']['list']['properties']['date'])->not->toHaveKey('nullable');
});

<?php

declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestPayload;

it('filters empty payload values consistently', function () {
    $filtered = RequestPayload::filterEmptyValues([
        'keep_int' => 0,
        'keep_false' => false,
        'drop_null' => null,
        'drop_empty_array' => [],
        'drop_empty_string' => '',
        'keep_text' => 'value',
    ]);

    expect($filtered)->toBe([
        'keep_int' => 0,
        'keep_false' => false,
        'keep_text' => 'value',
    ]);
});

it('normalizes supported response format types', function () {
    $text = new InferenceRequest(messages: Messages::empty(), responseFormat: ResponseFormat::text());
    $jsonObject = new InferenceRequest(messages: Messages::empty(), responseFormat: ResponseFormat::jsonObject());
    $jsonSchema = new InferenceRequest(
        messages: Messages::empty(),
        responseFormat: ResponseFormat::jsonSchema(['type' => 'object'], 'person'),
    );

    expect(RequestPayload::responseFormatType($text))->toBe('text')
        ->and(RequestPayload::responseFormatType($jsonObject))->toBe('json_object')
        ->and(RequestPayload::responseFormatType($jsonSchema))->toBe('json_schema');
});

it('removes provider-disallowed schema keys recursively', function () {
    $schema = [
        'type' => 'object',
        'x-title' => 'Person',
        'properties' => [
            'name' => [
                'type' => 'string',
                'x-php-class' => 'UserName',
            ],
        ],
    ];

    expect(RequestPayload::removeSchemaKeys($schema, ['x-title', 'x-php-class']))->toBe([
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
            ],
        ],
    ]);
});

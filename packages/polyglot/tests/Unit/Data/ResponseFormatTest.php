<?php

use Cognesy\Polyglot\Inference\Data\ResponseFormat;

it('provides explicit constructors for text, json object, and json schema', function () {
    $text = ResponseFormat::text();
    $jsonObject = ResponseFormat::jsonObject();
    $jsonSchema = ResponseFormat::jsonSchema(
        schema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
        name: 'user',
        strict: false,
    );

    expect($text->toArray())->toBe(['type' => 'text']);
    expect($jsonObject->toArray())->toBe(['type' => 'json_object']);
    expect($jsonSchema->toArray())->toBe([
        'type' => 'json_schema',
        'schema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
        'name' => 'user',
        'strict' => false,
    ]);
});

it('serializes provider-facing shapes explicitly', function () {
    $responseFormat = ResponseFormat::jsonSchema(
        schema: ['type' => 'object'],
        name: 'payload',
    );

    expect($responseFormat->asText())->toBe(['type' => 'text']);
    expect($responseFormat->asJsonObject())->toBe(['type' => 'json_object']);
    expect($responseFormat->asJsonSchema())->toBe([
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'payload',
            'schema' => ['type' => 'object'],
            'strict' => true,
        ],
    ]);
});

it('applies handlers immutably', function () {
    $responseFormat = ResponseFormat::jsonSchema(
        schema: ['type' => 'object'],
    );

    $custom = $responseFormat
        ->withToJsonObjectHandler(fn() => ['type' => 'custom_json'])
        ->withToJsonSchemaHandler(fn() => ['type' => 'custom_schema']);

    expect($responseFormat->asJsonObject())->toBe(['type' => 'json_object']);
    expect($responseFormat->asJsonSchema())->toBe([
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'schema',
            'schema' => ['type' => 'object'],
            'strict' => true,
        ],
    ]);
    expect($custom->asJsonObject())->toBe(['type' => 'custom_json']);
    expect($custom->asJsonSchema())->toBe(['type' => 'custom_schema']);
});

it('round-trips from plain and nested arrays', function () {
    $plain = ResponseFormat::fromArray([
        'type' => 'json_schema',
        'schema' => ['type' => 'object'],
        'name' => 'plain',
        'strict' => false,
    ]);

    $nested = ResponseFormat::fromArray([
        'type' => 'json_schema',
        'json_schema' => [
            'schema' => ['type' => 'object'],
            'name' => 'nested',
            'strict' => true,
        ],
    ]);

    expect($plain->toArray())->toBe([
        'type' => 'json_schema',
        'schema' => ['type' => 'object'],
        'name' => 'plain',
        'strict' => false,
    ]);
    expect($nested->toArray())->toBe([
        'type' => 'json_schema',
        'schema' => ['type' => 'object'],
        'name' => 'nested',
        'strict' => true,
    ]);
});

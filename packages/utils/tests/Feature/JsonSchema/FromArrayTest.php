<?php

use Cognesy\Utils\JsonSchema\JsonSchema;

test('fromArray with empty array returns null', function () {
    $result = JsonSchema::fromArray([]);

    expect($result)->toBeNull();
});

test('fromArray without type throws exception', function () {
    expect(fn() => JsonSchema::fromArray(['name' => 'empty']))
        ->toThrow(Exception::class, 'Invalid schema: missing "type"');
});

test('fromArray creates string schema', function () {
    $array = [
        'type' => 'string',
        'nullable' => true,
        'description' => 'User name',
        'title' => 'Name',
        'x-format' => 'text',
    ];

    $schema = JsonSchema::fromArray($array);

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('string')
        ->and($schema->isNullable())->toBeTrue()
        ->and($schema->description())->toBe('User name')
        ->and($schema->title())->toBe('Name')
        ->and($schema->meta())->toHaveKey('format')
        ->and($schema->meta('format'))->toBe('text');
});

test('fromArray creates enum schema', function () {
    $array = [
        'type' => 'string',
        'enum' => ['pending', 'active', 'inactive'],
        'description' => 'User status',
    ];

    $schema = JsonSchema::fromArray($array);

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('string')
        ->and($schema->enumValues())->toBe(['pending', 'active', 'inactive'])
        ->and($schema->description())->toBe('User status');
});

test('fromArray creates object schema with properties', function () {
    $array = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
        ],
        'required' => ['id', 'name'],
        'description' => 'User object',
    ];

    $schema = JsonSchema::fromArray($array);

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('object')
        ->and($schema->properties())->toHaveCount(2)
        ->and($schema->requiredProperties())->toBe(['id', 'name'])
        ->and($schema->description())->toBe('User object');

    // Check properties
    expect($schema->property('id'))->toBeInstanceOf(JsonSchema::class)
        ->and($schema->property('id')->type())->toBe('string')
        ->and($schema->property('name'))->toBeInstanceOf(JsonSchema::class)
        ->and($schema->property('name')->type())->toBe('string');
});

test('fromArray creates array schema with items', function () {
    $array = [
        'type' => 'array',
        'items' => [
            'type' => 'string',
        ],
        'description' => 'String array',
    ];

    $schema = JsonSchema::fromArray($array);

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('array')
        ->and($schema->description())->toBe('String array')
        ->and($schema->itemSchema())->toBeObject();

    // Check items schema
    $itemSchema = $schema->itemSchema();
    expect($itemSchema->type())->toBe('string');
});

test('fromArray creates complex nested schema', function () {
    $array = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'address' => [
                'type' => 'object',
                'properties' => [
                    'street' => ['type' => 'string'],
                    'city' => ['type' => 'string'],
                ],
            ],
            'tags' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                ],
            ],
        ],
        'required' => ['id', 'name'],
    ];

    $schema = JsonSchema::fromArray($array);

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('object')
        ->and($schema->properties())->toHaveCount(4)
        ->and($schema->requiredProperties())->toBe(['id', 'name']);

    // Check address object
    expect($schema->property('address'))->toBeInstanceOf(JsonSchema::class)
        ->and($schema->property('address')->type())->toBe('object')
        ->and($schema->property('address')->properties())->toHaveCount(2);

    // Check tags array
    expect($schema->property('tags'))->toBeInstanceOf(JsonSchema::class)
        ->and($schema->property('tags')->type())->toBe('array')
        ->and($schema->property('tags')->itemSchema())->toBeInstanceOf(JsonSchema::class);
});

test('fromArray extracts meta fields', function () {
    $array = [
        'type' => 'string',
        'description' => 'Email field',
        'x-format' => 'email',
        'x-example' => 'user@example.com',
        'x-min-length' => 5,
        'x-max-length' => 100,
    ];

    $schema = JsonSchema::fromArray($array);

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('string')
        ->and($schema->description())->toBe('Email field')
        ->and($schema->meta())->toHaveCount(4)
        ->and($schema->meta())->toHaveKeys(['format', 'example', 'min-length', 'max-length']);
});

test('fromArray with name parameter overrides x-name', function () {
    $array = [
        'type' => 'string',
        'x-name' => 'OriginalName',
    ];

    $schema = JsonSchema::fromArray($array, 'OverrideName');

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->name())->toBe('OverrideName');
});
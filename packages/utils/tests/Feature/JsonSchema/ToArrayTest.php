<?php

use Cognesy\Utils\JsonSchema\JsonSchema;

test('string schema conversion to array', function () {
    $schema = JsonSchema::string(
        description: 'User name',
        nullable: true,
        title: 'Name',
    );

    $array = $schema->toArray();

    expect($array)->toBe([
        'type' => 'string',
        'nullable' => true,
        'description' => 'User name',
        'title' => 'Name',
    ]);
});

test('enum schema conversion to array', function () {
    $schema = JsonSchema::enum(
        enumValues: ['pending', 'active', 'inactive'],
        description: 'User status',
    );

    $array = $schema->toArray();

    expect($array)->toBe([
        'type' => 'string',
        'description' => 'User status',
        'enum' => ['pending', 'active', 'inactive'],
    ]);
});

test('boolean schema conversion to array', function () {
    $schema = JsonSchema::boolean(
        description: 'Active status',
        nullable: false,
    );

    $array = $schema->toArray();

    expect($array)->toBe([
        'type' => 'boolean',
        'nullable' => false,
        'description' => 'Active status',
    ]);
});

test('number schema conversion to array', function () {
    $schema = JsonSchema::number(
        description: 'Product price',
        title: 'Price',
    );

    $array = $schema->toArray();

    expect($array)->toBe([
        'type' => 'number',
        'description' => 'Product price',
        'title' => 'Price',
    ]);
});

test('integer schema conversion to array', function () {
    $schema = JsonSchema::integer(
        description: 'Item count',
        nullable: true,
    );

    $array = $schema->toArray();

    expect($array)->toBe([
        'type' => 'integer',
        'nullable' => true,
        'description' => 'Item count',
    ]);
});

test('object schema conversion to array', function () {
    $schema = JsonSchema::object(
        description: 'User object',
        properties: [
            JsonSchema::string(name: 'id'),
            JsonSchema::string(name: 'name'),
        ],
        requiredProperties: ['id', 'name'],
        additionalProperties: false,
    );

    $array = $schema->toArray();

    expect($array)->toBeArray()
        ->and($array['type'])->toBe('object')
        ->and($array['description'])->toBe('User object')
        ->and($array['properties'])->toBeArray()
        ->and($array['required'])->toBe(['id', 'name'])
        ->and($array['additionalProperties'])->toBeFalse();

    // Check properties structure
    expect($array['properties']['id']['type'])->toBe('string')
        ->and($array['properties']['name']['type'])->toBe('string');
});

test('array schema conversion to array', function () {
    $schema = JsonSchema::array(
        description: 'List of users',
        itemSchema: JsonSchema::object(
            name: 'User',
            properties: [
                JsonSchema::string(name: 'id'),
                JsonSchema::string(name: 'name'),
            ]
        ),
    );

    $array = $schema->toArray();

    expect($array)->toBeArray()
        ->and($array['type'])->toBe('array')
        ->and($array['description'])->toBe('List of users')
        ->and($array['items'])->toBeArray();

    // Check items structure
    expect($array['items']['type'])->toBe('object')
        ->and($array['items']['properties'])->toBeArray();
});

test('schema with meta data conversion to array', function () {
    $schema = JsonSchema::string(
        description: 'User email',
        meta: ['format' => 'email', 'example' => 'user@example.com'],
    );

    $array = $schema->toArray();

    expect($array)->toBeArray()
        ->and($array['type'])->toBe('string')
        ->and($array['description'])->toBe('User email')
        ->and($array['x-format'])->toBe('email')
        ->and($array['x-example'])->toBe('user@example.com');
});

test('complex nested schema conversion to array', function () {
    $schema = JsonSchema::object(
        description: 'User with address',
        properties: [
            JsonSchema::string(name: 'id'),
            JsonSchema::string(name: 'name'),
            JsonSchema::object(
                name: 'address',
                properties: [
                    JsonSchema::string(name: 'street'),
                    JsonSchema::string(name: 'city'),
                    JsonSchema::string(name: 'country'),
                ]
            ),
            JsonSchema::array(
                name: 'phones',
                itemSchema: JsonSchema::string(name: 'phone')
            ),
        ],
        requiredProperties: ['id', 'name'],
    );

    $array = $schema->toArray();

    expect($array)->toBeArray()
        ->and($array['type'])->toBe('object')
        ->and($array['properties'])->toBeArray()
        ->and($array['properties'])->toHaveCount(4)
        ->and($array['required'])->toBe(['id', 'name']);

    // Check nested address object
    expect($array['properties']['address']['type'])->toBe('object')
        ->and($array['properties']['address']['properties'])->toBeArray()
        ->and($array['properties']['address']['properties'])->toHaveCount(3);

    // Check nested phones array
    expect($array['properties']['phones']['type'])->toBe('array')
        ->and($array['properties']['phones']['items'])->toBeArray();
});
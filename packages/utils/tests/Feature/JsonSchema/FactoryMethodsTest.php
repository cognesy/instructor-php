<?php

use Cognesy\Utils\JsonSchema\JsonSchema;

test('object schema can be created using factory method', function () {
    $schema = JsonSchema::object(
        name: 'User',
        description: 'User object',
        properties: [
            JsonSchema::string(name: 'id'),
            JsonSchema::string(name: 'name'),
        ],
        requiredProperties: ['id', 'name'],
    );

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('object')
        ->and($schema->name())->toBe('User')
        ->and($schema->description())->toBe('User object')
        ->and($schema->properties())->toHaveCount(2)
        ->and($schema->requiredProperties())->toBe(['id', 'name']);
});

test('array schema can be created using factory method', function () {
    $schema = JsonSchema::array(
        name: 'UserList',
        description: 'List of users',
        itemSchema: JsonSchema::object(
            name: 'User',
            properties: [
                JsonSchema::string(name: 'id'),
                JsonSchema::string(name: 'name'),
            ]
        ),
    );

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('array')
        ->and($schema->name())->toBe('UserList')
        ->and($schema->description())->toBe('List of users')
        ->and($schema->itemSchema)->not->toBeEmpty();
});

test('string schema can be created using factory method', function () {
    $schema = JsonSchema::string(
        name: 'UserName',
        description: 'User name',
        nullable: true
    );

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('string')
        ->and($schema->name())->toBe('UserName')
        ->and($schema->description())->toBe('User name')
        ->and($schema->isNullable())->toBeTrue();
});

test('boolean schema can be created using factory method', function () {
    $schema = JsonSchema::boolean(
        name: 'IsActive',
        description: 'Active status',
        title: 'Active'
    );

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('boolean')
        ->and($schema->name())->toBe('IsActive')
        ->and($schema->description())->toBe('Active status')
        ->and($schema->title)->toBe('Active');
});

test('number schema can be created using factory method', function () {
    $schema = JsonSchema::number(
        name: 'Price',
        description: 'Product price',
        nullable: false
    );

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('number')
        ->and($schema->name())->toBe('Price')
        ->and($schema->description())->toBe('Product price')
        ->and($schema->isNullable())->toBeFalse();
});

test('integer schema can be created using factory method', function () {
    $schema = JsonSchema::integer(
        name: 'Count',
        description: 'Item count',
        nullable: true
    );

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('integer')
        ->and($schema->name())->toBe('Count')
        ->and($schema->description())->toBe('Item count')
        ->and($schema->isNullable())->toBeTrue();
});

test('enum schema can be created using factory method', function () {
    $schema = JsonSchema::enum(
        name: 'Status',
        enumValues: ['pending', 'active', 'inactive'],
        description: 'User status'
    );

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('string')
        ->and($schema->name())->toBe('Status')
        ->and($schema->enumValues)->toBe(['pending', 'active', 'inactive'])
        ->and($schema->description())->toBe('User status');
});
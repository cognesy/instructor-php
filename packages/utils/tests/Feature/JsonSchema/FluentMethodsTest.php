<?php

use Cognesy\Utils\JsonSchema\JsonSchema;

test('schema can be modified using withName', function () {
    $schema = JsonSchema::object()
        ->withName('User');

    expect($schema->name())->toBe('User');
});

test('schema can be modified using withDescription', function () {
    $schema = JsonSchema::object()
        ->withDescription('User object description');

    expect($schema->description())->toBe('User object description');
});

test('schema can be modified using withTitle', function () {
    $schema = JsonSchema::object()
        ->withTitle('User Model');

    expect($schema->title())->toBe('User Model');
});

test('schema can be modified using withNullable', function () {
    $schema = JsonSchema::string()
        ->withNullable(true);

    expect($schema->isNullable())->toBeTrue();

    $schema = JsonSchema::string()
        ->withNullable(false);

    expect($schema->isNullable())->toBeFalse();
});

test('schema can be modified using withMeta', function () {
    $meta = ['format' => 'email', 'example' => 'user@example.com'];
    $schema = JsonSchema::string()
        ->withMeta($meta);

    expect($schema->meta())->toBe($meta);
});

test('string schema can be modified using withEnum', function () {
    $enum = ['pending', 'active', 'inactive'];
    $schema = JsonSchema::string()
        ->withEnumValues($enum);

    expect($schema->enumValues())->toBe($enum);
});

test('object schema can be modified using withProperties', function () {
    $properties = [
        JsonSchema::string(name: 'id'),
        JsonSchema::string(name: 'name'),
    ];

    $schema = JsonSchema::object()
        ->withProperties($properties);

    expect($schema->properties())->toHaveCount(2)
        ->and(array_keys($schema->properties()))->toBe(['id', 'name']);
});

test('array schema can be modified using withItemSchema', function () {
    $itemSchema = JsonSchema::string(name: 'item');

    $schema = JsonSchema::array()
        ->withItemSchema($itemSchema);

    expect($schema->itemSchema())->not->toBeEmpty();
});

test('object schema can be modified using withRequired', function () {
    $required = ['id', 'name'];

    $schema = JsonSchema::object(
        properties: [
            JsonSchema::string(name: 'id'),
            JsonSchema::string(name: 'name'),
            JsonSchema::string(name: 'description'),
        ]
    )->withRequiredProperties($required);

    expect($schema->requiredProperties())->toBe($required);
});

test('object schema can be modified using withAdditionalProperties', function () {
    $schema = JsonSchema::object()
        ->withAdditionalProperties(true);

    expect($schema->hasAdditionalProperties())->toBeTrue();

    $schema = JsonSchema::object()
        ->withAdditionalProperties(false);

    expect($schema->hasAdditionalProperties())->toBeFalse();
});

test('fluent methods can be chained', function () {
    $schema = JsonSchema::object()
        ->withName('User')
        ->withDescription('User object')
        ->withTitle('User Model')
        ->withNullable(true)
        ->withProperties([
            JsonSchema::string(name: 'id'),
            JsonSchema::string(name: 'name'),
        ])
        ->withRequiredProperties(['id', 'name'])
        ->withAdditionalProperties(false)
        ->withMeta(['example' => ['id' => '1', 'name' => 'John']]);

    expect($schema->name())->toBe('User')
        ->and($schema->description())->toBe('User object')
        ->and($schema->title())->toBe('User Model')
        ->and($schema->isNullable())->toBeTrue()
        ->and($schema->properties())->toHaveCount(2)
        ->and($schema->requiredProperties())->toBe(['id', 'name'])
        ->and($schema->hasAdditionalProperties())->toBeFalse()
        ->and($schema->meta())->toBe(['example' => ['id' => '1', 'name' => 'John']]);
});
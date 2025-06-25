<?php

use Cognesy\Utils\JsonSchema\JsonSchema;

test('object schema with properties using different formats', function () {
    // Test with array of JsonSchema objects
    $schema1 = JsonSchema::object(
        name: 'Test1',
        properties: [
            JsonSchema::string(name: 'prop1'),
            JsonSchema::number(name: 'prop2'),
        ],
    );

    // Test with associative array with string keys
    $schema2 = JsonSchema::object(
        name: 'Test2',
        properties: [
            'prop1' => JsonSchema::string(),
            'prop2' => JsonSchema::number(),
        ],
    );

    // Test with mixed format
    $schema3 = JsonSchema::object(
        name: 'Test3',
        properties: [
            JsonSchema::string(name: 'prop1'),
            'prop2' => JsonSchema::number(),
        ],
    );

    expect($schema1->properties())->toHaveCount(2)
        ->and($schema2->properties())->toHaveCount(2)
        ->and($schema3->properties())->toHaveCount(2);

    expect(array_keys($schema1->properties()))->toBe(['prop1', 'prop2'])
        ->and(array_keys($schema2->properties()))->toBe(['prop1', 'prop2'])
        ->and(array_keys($schema3->properties()))->toBe(['prop1', 'prop2']);

    // Check that property types are preserved correctly
    expect($schema1->property('prop1')->type()->toString())->toBe('string')
        ->and($schema1->property('prop2')->type()->toString())->toBe('number')
        ->and($schema2->property('prop1')->type()->toString())->toBe('string')
        ->and($schema2->property('prop2')->type()->toString())->toBe('number')
        ->and($schema3->property('prop1')->type()->toString())->toBe('string')
        ->and($schema3->property('prop2')->type()->toString())->toBe('number');
});

test('withProperties replaces existing properties', function () {
    $schema = JsonSchema::object(
        name: 'Test',
        properties: [
            JsonSchema::string(name: 'original1'),
            JsonSchema::string(name: 'original2'),
        ],
    );

    $newSchema = $schema->withProperties([
        JsonSchema::string(name: 'new1'),
        JsonSchema::string(name: 'new2'),
        JsonSchema::string(name: 'new3'),
    ]);

    expect($newSchema->properties())->toHaveCount(3)
        ->and(array_keys($newSchema->properties()))->toBe(['new1', 'new2', 'new3']);
});

test('withItemSchema replaces existing items', function () {
    $schema = JsonSchema::array(
        name: 'TestArray',
        itemSchema: JsonSchema::string(name: 'originalItem')
    );

    $newItemSchema = JsonSchema::number(name: 'newItem');
    $newSchema = $schema->withItemSchema($newItemSchema);

    $array = $newSchema->toArray();
    expect($array['items']['type'])->toBe('number');
});

test('required properties are validated against existing properties', function () {
    $schema = JsonSchema::object(
        name: 'Test',
        properties: [
            JsonSchema::string(name: 'id'),
            JsonSchema::string(name: 'name'),
            JsonSchema::string(name: 'optional'),
        ],
        requiredProperties: ['id', 'name'],
    );

    expect($schema->requiredProperties())->toBe(['id', 'name']);
});

test('meta data is properly stored and retrieved', function () {
    $meta = [
        'format' => 'email',
        'example' => 'user@example.com',
        'minimum' => 0,
        'maximum' => 100,
    ];

    $schema = JsonSchema::string(
        name: 'Email',
        meta: $meta,
    );

    expect($schema->meta())->toBe($meta);

    // Check that meta data is correctly added to the array output
    $array = $schema->toArray();
    expect($array['x-format'])->toBe('email')
        ->and($array['x-example'])->toBe('user@example.com')
        ->and($array['x-minimum'])->toBe(0)
        ->and($array['x-maximum'])->toBe(100);
});

test('additional properties flag is stored and retrieved', function () {
    $schema = JsonSchema::object(
        name: 'Test',
        additionalProperties: true,
    );

    expect($schema->hasAdditionalProperties())->toBeTrue();

    $schema = JsonSchema::object(
        name: 'Test',
        additionalProperties: false,
    );

    expect($schema->hasAdditionalProperties())->toBeFalse();

    // Check array output
    $array = $schema->toArray();
    expect($array['additionalProperties'])->toBeFalse();
});

test('nullable flag is handled properly', function () {
    $schema = JsonSchema::string(
        name: 'Test',
        nullable: true,
    );

    expect($schema->isNullable())->toBeTrue();

    // Check array output
    $array = $schema->toArray();
    expect($array['nullable'])->toBeTrue();

    // Test with nullable set to false
    $schema = JsonSchema::string(
        name: 'Test',
        nullable: false,
    );

    expect($schema->isNullable())->toBeFalse();

    // Check array output
    $array = $schema->toArray();
    expect($array['nullable'])->toBeFalse();
});

test('appendMeta prefixes non-prefixed keys with x-', function () {
    $reflectionClass = new ReflectionClass(JsonSchema::class);
    $method = $reflectionClass->getMethod('appendMeta');
    $method->setAccessible(true);

    $schema = JsonSchema::fromArray(['type' => 'string']);
    $values = ['type' => 'string'];
    $meta = [
        'already-prefixed' => 'value1',
        'x-prefixed' => 'value2',
        'needs-prefix' => 'value3',
    ];

    $result = $method->invoke($schema, $values, $meta);

    expect($result)->toHaveKey('x-already-prefixed')
        ->and($result)->toHaveKey('x-prefixed')
        ->and($result)->toHaveKey('x-needs-prefix');
});
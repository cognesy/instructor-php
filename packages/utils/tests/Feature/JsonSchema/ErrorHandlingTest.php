<?php

use Cognesy\Utils\JsonSchema\JsonSchema;

test('constructor throws exception with invalid type', function () {
    expect(function () {
        new JsonSchema(
            type: 'invalid',
            name: 'Test'
        );
    })->toThrow(Exception::class, 'Invalid type: invalid');
});

test('toArray throws exception with invalid type', function () {
    // Create a reflection of JsonSchema to bypass constructor validation
    $reflectionClass = new ReflectionClass(JsonSchema::class);
    $schema = $reflectionClass->newInstanceWithoutConstructor();

    // Set invalid type via reflection
    $reflectionProp = $reflectionClass->getProperty('type');
    $reflectionProp->setAccessible(true);
    $reflectionProp->setValue($schema, 'invalid');

    // Set required name property
    $reflectionName = $reflectionClass->getProperty('name');
    $reflectionName->setAccessible(true);
    $reflectionName->setValue($schema, 'Test');

    expect(function () use ($schema) {
        $schema->toArray();
    })->toThrow(Exception::class, 'Invalid type: invalid');
});

test('toFunctionCall throws exception when called on non-object schema', function () {
    $schema = JsonSchema::string(name: 'TestString');

    expect(function () use ($schema) {
        $schema->toFunctionCall('testFunction');
    })->toThrow(Exception::class, 'Cannot convert to function call: string');
});

test('fromArray throws exception when type is missing', function () {
    $data = [
        'description' => 'Test description',
    ];

    expect(function () use ($data) {
        JsonSchema::fromArray($data);
    })->toThrow(Exception::class, 'Invalid schema: missing "type"');
});

test('toKeyedProperties throws exception when property name is missing', function () {
    $reflectionClass = new ReflectionClass(JsonSchema::class);
    $method = $reflectionClass->getMethod('toKeyedProperties');
    $method->setAccessible(true);

    // Array with missing property name
    $invalidProperties = [
        ['type' => 'string'], // missing name
    ];

    expect(function () use ($method, $invalidProperties) {
        $method->invoke(null, $invalidProperties);
    })->toThrow(Exception::class, 'Missing property name:');
});

test('toKeyedProperties throws exception when property is invalid', function () {
    $reflectionClass = new ReflectionClass(JsonSchema::class);
    $method = $reflectionClass->getMethod('toKeyedProperties');
    $method->setAccessible(true);

    // Invalid property type (neither JsonSchema nor array)
    $invalidProperties = [
        'invalid' => 'string', // Not a JsonSchema or array
    ];

    expect(function () use ($method, $invalidProperties) {
        $method->invoke(null, $invalidProperties);
    })->toThrow(Exception::class, 'Invalid property:');
});

test('constructor validates enum values are strings', function () {
    expect(function () {
        new JsonSchema(
            type: 'string',
            name: 'Test',
            enumValues: ['valid', 123, true] // mixed types: string, number, boolean
        );
    })->toThrow(Exception::class, 'Invalid enum value:');
});

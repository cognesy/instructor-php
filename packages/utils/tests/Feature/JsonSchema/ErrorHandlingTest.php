<?php

use Cognesy\Utils\JsonSchema\JsonSchema;

test('constructor throws exception with invalid type', function () {
    expect(function () {
        JsonSchema::fromArray(['type' => 'invalid']);
    })->toThrow(Exception::class,  'Invalid JSON type: invalid in:');
});

test('toFunctionCall throws exception when called on non-object schema', function () {
    $schema = JsonSchema::string(name: 'TestString');

    expect(function () use ($schema) {
        $schema->toFunctionCall('testFunction');
    })->toThrow(Exception::class, 'Cannot convert to function call: string');
});

// EXCLUDED - This is valid schema
//test('fromArray throws exception when type is missing', function () {
//    $data = [
//        'description' => 'Test description',
//    ];
//
//    expect(function () use ($data) {
//        JsonSchema::fromArray($data);
//    })->toThrow(Exception::class, 'Invalid schema: missing "type"');
//});

test('toKeyedProperties throws exception when property name is missing', function () {
    $reflectionClass = new ReflectionClass(JsonSchema::class);
    $method = $reflectionClass->getMethod('toKeyedProperties');
    

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
        JsonSchema::fromArray(['type' => 'string', 'name' => 'Test', 'enum' => ['valid', 123, true]]);
    })->toThrow(Exception::class, 'Invalid JSON type: invalid in:');
});

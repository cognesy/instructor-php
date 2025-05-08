<?php

use Cognesy\Utils\JsonSchema\JsonSchema;

test('toFunctionCall converts object schema to function call format', function () {
    $schema = JsonSchema::object(
        name: 'User',
        properties: [
            JsonSchema::string(name: 'id'),
            JsonSchema::string(name: 'name'),
        ],
        requiredProperties: ['id', 'name'],
    );

    $functionCall = $schema->toFunctionCall(
        functionName: 'createUser',
        functionDescription: 'Create a new user',
        strict: true,
    );

    expect($functionCall)->toBeArray()
        ->and($functionCall['type'])->toBe('function')
        ->and($functionCall['function'])->toBeArray()
        ->and($functionCall['function']['name'])->toBe('createUser')
        ->and($functionCall['function']['description'])->toBe('Create a new user')
        ->and($functionCall['function']['parameters'])->toBeArray()
        ->and($functionCall['strict'])->toBeTrue();

    // Check parameters structure
    expect($functionCall['function']['parameters']['type'])->toBe('object')
        ->and($functionCall['function']['parameters']['properties'])->toBeArray()
        ->and($functionCall['function']['parameters']['required'])->toBe(['id', 'name']);
});

test('toFunctionCall with minimal parameters', function () {
    $schema = JsonSchema::object(
        name: 'SimpleObject',
        properties: [
            JsonSchema::string(name: 'field'),
        ],
    );

    $functionCall = $schema->toFunctionCall(
        functionName: 'simpleFunction',
    );

    expect($functionCall)->toBeArray()
        ->and($functionCall['type'])->toBe('function')
        ->and($functionCall['function']['name'])->toBe('simpleFunction')
        ->and($functionCall['function']['description'])->toBe('')
        ->and($functionCall['strict'])->toBeFalse();
});

test('toFunctionCall fails on non-object schema', function () {
    $schema = JsonSchema::string('name');

    expect(fn() => $schema->toFunctionCall('testFunction'))
        ->toThrow(Exception::class, 'Cannot convert to function call: string');
});

test('toFunctionCall with complex nested object schema', function () {
    $schema = JsonSchema::object(
        name: 'ComplexUser',
        properties: [
            JsonSchema::string(name: 'id'),
            JsonSchema::string(name: 'name'),
            JsonSchema::object(
                name: 'contact',
                properties: [
                    JsonSchema::string(name: 'email'),
                    JsonSchema::string(name: 'phone'),
                ],
                requiredProperties: ['email'],
            ),
            JsonSchema::array(
                name: 'roles',
                itemSchema: JsonSchema::string(),
            ),
        ],
        requiredProperties: ['id', 'name', 'contact'],
    );

    $functionCall = $schema->toFunctionCall(
        functionName: 'createComplexUser',
        functionDescription: 'Create a user with contact details and roles',
    );

    expect($functionCall)->toBeArray()
        ->and($functionCall['function']['name'])->toBe('createComplexUser')
        ->and($functionCall['function']['parameters']['properties'])->toHaveCount(4)
        ->and($functionCall['function']['parameters']['required'])->toBe(['id', 'name', 'contact']);

    // Check nested contact object
    $propertiesArray = $functionCall['function']['parameters']['properties'];
    expect($propertiesArray['contact']['type'])->toBe('object')
        ->and($propertiesArray['contact']['properties'])->toHaveCount(2)
        ->and($propertiesArray['contact']['required'])->toBe(['email']);

    // Check roles array
    expect($propertiesArray['roles']['type'])->toBe('array');
});
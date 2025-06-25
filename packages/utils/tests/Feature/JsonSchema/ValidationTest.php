<?php

use Cognesy\Utils\JsonSchema\JsonSchema;

test('validation fails with invalid type', function () {
    expect(function () {
        JsonSchema::fromArray(['type' => 'invalid', 'name' => 'Test']);
    })->toThrow(Exception::class, 'Invalid JSON type: invalid in:');
});

test('validation fails with invalid enum value', function () {
    expect(function () {
        JsonSchema::fromArray(['type' => 'enum', 'name' => 'Test', 'enumValues' => ['one', 2, true]]);
    })->toThrow(Exception::class, 'Invalid JSON type: enum in:');
});

test('object schema validates property types correctly', function () {
    // Valid schema with different property types
    $validSchema = JsonSchema::object(
        name: 'ValidObject',
        properties: [
            JsonSchema::string(name: 'stringProp'),
            JsonSchema::number(name: 'numberProp'),
            JsonSchema::boolean(name: 'boolProp'),
            JsonSchema::integer(name: 'integerProp'),
            JsonSchema::object(
                name: 'objectProp',
                properties: [
                    JsonSchema::string(name: 'nestedProp')
                ]
            ),
            JsonSchema::array(
                name: 'arrayProp',
                itemSchema: JsonSchema::string(name: 'arrayItem')
            ),
        ]
    );

    expect($validSchema)->toBeInstanceOf(JsonSchema::class)
        ->and($validSchema->properties())->toHaveCount(6);
});

test('object schema with required properties validation', function () {
    // This should pass validation because all required properties exist
    $validSchema = JsonSchema::object(
        name: 'ValidObject',
        properties: [
            JsonSchema::string(name: 'id'),
            JsonSchema::string(name: 'name'),
            JsonSchema::string(name: 'optional'),
        ],
        requiredProperties: ['id', 'name'],
    );

    expect($validSchema)->toBeInstanceOf(JsonSchema::class)
        ->and($validSchema->requiredProperties())->toBe(['id', 'name']);
});

test('array schema item validation works', function () {
    // Valid array schema
    $validArraySchema = JsonSchema::array(
        name: 'ValidArray',
        itemSchema: JsonSchema::string(name: 'item')
    );

    expect($validArraySchema)->toBeInstanceOf(JsonSchema::class)
        ->and($validArraySchema->itemSchema())->toBeTruthy();

    // Array with object items
    $arrayWithObjectsSchema = JsonSchema::array(
        name: 'ArrayWithObjects',
        itemSchema: JsonSchema::object(
            name: 'ObjectItem',
            properties: [
                JsonSchema::string(name: 'prop')
            ]
        )
    );

    expect($arrayWithObjectsSchema)->toBeInstanceOf(JsonSchema::class);

    // Array with nested array items
    $nestedArraySchema = JsonSchema::array(
        name: 'NestedArray',
        itemSchema: JsonSchema::array(
            name: 'InnerArray',
            itemSchema: JsonSchema::string(name: 'innerItem')
        )
    );

    expect($nestedArraySchema)->toBeInstanceOf(JsonSchema::class);
});

test('validation of enum allows only string values', function () {
    // Valid enum with strings only
    $validEnum = JsonSchema::enum(
        name: 'ValidEnum',
        enumValues: ['one', 'two', 'three']
    );

    expect($validEnum)->toBeInstanceOf(JsonSchema::class)
        ->and($validEnum->enumValues())->toBe(['one', 'two', 'three']);

    // Invalid enum with non-string values should throw exception
    expect(function () {
        JsonSchema::enum(
            name: 'InvalidEnum',
            enumValues: ['one', 2, true]
        );
    })->toThrow(Exception::class);
});

test('complex nested schema validation works', function () {
    $complexSchema = JsonSchema::object(
        name: 'ComplexObject',
        properties: [
            JsonSchema::string(name: 'id'),
            JsonSchema::object(
                name: 'nested',
                properties: [
                    JsonSchema::string(name: 'nestedProp'),
                    JsonSchema::array(
                        name: 'nestedArray',
                        itemSchema: JsonSchema::string(name: 'nestedItem')
                    )
                ]
            ),
            JsonSchema::array(
                name: 'items',
                itemSchema: JsonSchema::object(
                    name: 'arrayItem',
                    properties: [
                        JsonSchema::string(name: 'itemProp')
                    ]
                )
            )
        ]
    );

    expect($complexSchema)->toBeInstanceOf(JsonSchema::class)
        ->and($complexSchema->properties())->toHaveCount(3);
});
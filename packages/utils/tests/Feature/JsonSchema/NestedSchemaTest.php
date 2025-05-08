<?php

use Cognesy\Utils\JsonSchema\JsonSchema;

test('object schema can have nested object properties', function () {
    $schema = JsonSchema::object(
        name: 'UserWithAddress',
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
        ],
        requiredProperties: ['id', 'name'],
    );

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->properties())->toHaveCount(3)
        ->and($schema->property('address'))->toBeInstanceOf(JsonSchema::class)
        ->and($schema->property('address')->type())->toBe('object')
        ->and($schema->property('address')->properties())->toHaveCount(3);

    // Convert to array and check nested structure
    $array = $schema->toArray();
    expect($array['properties'])->toHaveCount(3)
        ->and($array['properties']['address']['type'])->toBe('object')
        ->and($array['properties']['address']['properties'])->toHaveCount(3);
});

test('object schema can have nested array properties', function () {
    $schema = JsonSchema::object(
        name: 'UserWithTags',
        properties: [
            JsonSchema::string(name: 'id'),
            JsonSchema::string(name: 'name'),
            JsonSchema::array(
                name: 'tags',
                itemSchema: JsonSchema::string(name: 'tag')
            ),
        ],
    );

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->properties())->toHaveCount(3)
        ->and($schema->property('tags'))->toBeInstanceOf(JsonSchema::class)
        ->and($schema->property('tags')->type())->toBe('array');

    // Convert to array and check nested structure
    $array = $schema->toArray();
    expect($array['properties'])->toHaveCount(3)
        ->and($array['properties']['tags']['type'])->toBe('array')
        ->and($array['properties']['tags']['items'])->toBeTruthy();
});

test('array schema can have object items', function () {
    $schema = JsonSchema::array(
        name: 'UserList',
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
        ->and($schema->itemSchema())->toBeTruthy();

    // Convert to array and check nested structure
    $array = $schema->toArray();
    expect($array['type'])->toBe('array')
        ->and($array['items'])->toBeTruthy()
        ->and($array['items']['type'])->toBe('object')
        ->and($array['items']['properties'])->toHaveCount(2);
});

test('array schema can have array items', function () {
    $schema = JsonSchema::array(
        name: 'Matrix',
        itemSchema: JsonSchema::array(
            name: 'Row',
            itemSchema: JsonSchema::number(name: 'Cell')
        )
    );

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('array');

    // Convert to array and check nested structure
    $array = $schema->toArray();
    expect($array['type'])->toBe('array')
        ->and($array['items'])->toBeTruthy()
        ->and($array['items']['type'])->toBe('array')
        ->and($array['items']['items'])->toBeTruthy()
        ->and($array['items']['items']['type'])->toBe('number');
});

test('deeply nested schema with multiple levels', function () {
    $schema = JsonSchema::object(
        name: 'Organization',
        properties: [
            JsonSchema::string(name: 'id'),
            JsonSchema::string(name: 'name'),
            JsonSchema::array(
                name: 'departments',
                itemSchema: JsonSchema::object(
                    name: 'Department',
                    properties: [
                        JsonSchema::string(name: 'id'),
                        JsonSchema::string(name: 'name'),
                        JsonSchema::array(
                            name: 'employees',
                            itemSchema: JsonSchema::object(
                                name: 'Employee',
                                properties: [
                                    JsonSchema::string(name: 'id'),
                                    JsonSchema::string(name: 'name'),
                                    JsonSchema::object(
                                        name: 'contact',
                                        properties: [
                                            JsonSchema::string(name: 'email'),
                                            JsonSchema::string(name: 'phone'),
                                        ]
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            ),
        ],
    );

    expect($schema)->toBeInstanceOf(JsonSchema::class);

    // Convert to array and check nested structure
    $array = $schema->toArray();

    // Level 1: Organization
    expect($array['type'])->toBe('object')
        ->and($array['properties'])->toHaveCount(3);

    // Level 2: departments array
    $departments = $array['properties']['departments'];
    expect($departments['type'])->toBe('array');

    // Level 3: Department object in departments array
    $department = $departments['items'];
    expect($department['type'])->toBe('object')
        ->and($department['properties'])->toHaveCount(3);

    // Level 4: employees array in Department
    $employees = $department['properties']['employees'];
    expect($employees['type'])->toBe('array');

    // Level 5: Employee object in employees array
    $employee = $employees['items'];
    expect($employee['type'])->toBe('object')
        ->and($employee['properties'])->toHaveCount(3);

    // Level 6: contact object in Employee
    $contact = $employee['properties']['contact'];
    expect($contact['type'])->toBe('object')
        ->and($contact['properties'])->toHaveCount(2);
});

test('schema with differently keyed properties', function () {
    // Test with properties as array of JsonSchema objects
    $schema1 = JsonSchema::object(
        name: 'Test1',
        properties: [
            JsonSchema::string(name: 'prop1'),
            JsonSchema::string(name: 'prop2'),
        ],
    );

    // Test with properties as associative array with string keys
    $schema2 = JsonSchema::object(
        name: 'Test2',
        properties: [
            'prop1' => JsonSchema::string(),
            'prop2' => JsonSchema::string(),
        ],
    );

    // Test with properties as array of arrays
    $schema3 = JsonSchema::object(
        name: 'Test3',
        properties: [
            ['type' => 'string', 'name' => 'prop1'],
            ['type' => 'string', 'name' => 'prop2'],
        ],
    );

    expect($schema1->properties())->toHaveCount(2)
        ->and($schema2->properties())->toHaveCount(2)
        ->and($schema3->properties())->toHaveCount(2);

    expect(array_keys($schema1->properties()))->toBe(['prop1', 'prop2'])
        ->and(array_keys($schema2->properties()))->toBe(['prop1', 'prop2'])
        ->and(array_keys($schema3->properties()))->toBe(['prop1', 'prop2']);
});
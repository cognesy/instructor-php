<?php

use Cognesy\Utils\JsonSchema\JsonSchema;

test('schema can be created, converted to array, and converted back to schema', function () {
    // Create original schema
    $originalSchema = JsonSchema::object(
        name: 'User',
        description: 'User object',
        properties: [
            JsonSchema::string(name: 'id'),
            JsonSchema::string(name: 'name'),
            JsonSchema::number(name: 'age')
                ->withNullable(true),
            JsonSchema::array(name: 'tags')
                ->withItemSchema(JsonSchema::string()),
        ],
        requiredProperties: ['id', 'name'],
        additionalProperties: false,
    );

    // Convert to array
    $array = $originalSchema->toArray();

    // Convert back to schema
    $recreatedSchema = JsonSchema::fromArray($array);

    // Check that basic properties match
    expect($recreatedSchema->type()->toString())->toBe($originalSchema->type()->toString())
        ->and($recreatedSchema->description())->toBe($originalSchema->description())
        ->and(array_keys($recreatedSchema->properties()))->toBe(array_keys($originalSchema->properties()))
        ->and($recreatedSchema->requiredProperties())->toBe($originalSchema->requiredProperties())
        ->and($recreatedSchema->hasAdditionalProperties())->toBe($originalSchema->hasAdditionalProperties());

    // Check properties types
    expect($recreatedSchema->property('id')->type()->toString())->toBe('string')
        ->and($recreatedSchema->property('name')->type()->toString())->toBe('string')
        ->and($recreatedSchema->property('age')->type()->toString())->toBe('number')
        ->and($recreatedSchema->property('age')->isNullable())->toBeTrue()
        ->and($recreatedSchema->property('tags')->type()->toString())->toBe('array');
});

test('schema can be converted to function call and maintains structure', function () {
    // Create schema
    $schema = JsonSchema::object(
        name: 'UserCreation',
        description: 'Schema for creating a user',
        properties: [
            JsonSchema::string(name: 'username')
                ->withDescription('Unique username'),
            JsonSchema::string(name: 'email')
                ->withDescription('User email')
                ->withMeta(['format' => 'email']),
            JsonSchema::object(name: 'profile',
                properties: [
                    JsonSchema::string(name: 'firstName'),
                    JsonSchema::string(name: 'lastName'),
                ]
            ),
        ],
        requiredProperties: ['username', 'email'],
    );

    // Convert to function call
    $functionCall = $schema->toFunctionCall(
        functionName: 'createUser',
        functionDescription: 'Create a new user in the system',
        strict: true,
    );

    // Check function call structure
    expect($functionCall['type'])->toBe('function')
        ->and($functionCall['function']['name'])->toBe('createUser')
        ->and($functionCall['function']['description'])->toBe('Create a new user in the system')
        ->and($functionCall['strict'])->toBeTrue();

    // Check parameters structure
    $parameters = $functionCall['function']['parameters'];
    expect($parameters['type'])->toBe('object')
        ->and($parameters['description'])->toBe('Schema for creating a user')
        ->and($parameters['properties'])->toHaveCount(3)
        ->and($parameters['required'])->toBe(['username', 'email']);

    // Check property descriptions and meta data are maintained
    expect($parameters['properties']['username']['description'])->toBe('Unique username')
        ->and($parameters['properties']['email']['description'])->toBe('User email')
        ->and($parameters['properties']['email']['x-format'])->toBe('email');

    // Check nested object structure is maintained
    expect($parameters['properties']['profile']['type'])->toBe('object')
        ->and($parameters['properties']['profile']['properties'])->toHaveCount(2);
});

test('schema with different property definition styles can be converted correctly', function () {
    // Define a schema with various property definition styles
    $schema = JsonSchema::object(
        name: 'MixedPropertyStyles',
        properties: [
            // Style 1: Using factory method with name parameter
            JsonSchema::string(name: 'prop1'),

            // Style 2: Using associative array with string key
            'prop2' => JsonSchema::number(),

            // Style 3: Using withName fluent method
            JsonSchema::boolean()->withName('prop3'),

            // Style 4: Nesting objects with different styles
            JsonSchema::object(
                name: 'prop4',
                properties: [
                    JsonSchema::string(name: 'nested1'),
                    'nested2' => JsonSchema::number(),
                ]
            ),

            // Style 5: Using an array definition
            [
                'name' => 'prop5',
                'type' => 'string',
                'description' => 'Array style definition',
            ],
        ],
    );

    // Convert to array
    $array = $schema->toArray();

    // Check all properties are preserved correctly
    expect($array['properties'])->toHaveCount(5)
        ->and($array['properties']['prop1']['type'])->toBe('string')
        ->and($array['properties']['prop2']['type'])->toBe('number')
        ->and($array['properties']['prop3']['type'])->toBe('boolean')
        ->and($array['properties']['prop4']['type'])->toBe('object')
        ->and($array['properties']['prop4']['properties'])->toHaveCount(2)
        ->and($array['properties']['prop5']['type'])->toBe('string')
        ->and($array['properties']['prop5']['description'])->toBe('Array style definition');
});

test('full real-world example of API endpoint schema', function () {
    // Create a schema that represents a real API endpoint request/response schema

    // Pagination metadata
    $paginationSchema = JsonSchema::object(
        name: 'Pagination',
        properties: [
            JsonSchema::integer(name: 'page')
                ->withDescription('Current page number'),
            JsonSchema::integer(name: 'perPage')
                ->withDescription('Number of items per page'),
            JsonSchema::integer(name: 'totalItems')
                ->withDescription('Total number of items'),
            JsonSchema::integer(name: 'totalPages')
                ->withDescription('Total number of pages'),
        ],
        requiredProperties: ['page', 'perPage', 'totalItems', 'totalPages'],
    );

    // Error schema
    $errorSchema = JsonSchema::object(
        name: 'Error',
        properties: [
            JsonSchema::string(name: 'code')
                ->withDescription('Error code'),
            JsonSchema::string(name: 'message')
                ->withDescription('Error message'),
            JsonSchema::object(name: 'details')
                ->withDescription('Error details')
                ->withNullable(true),
        ],
        requiredProperties: ['code', 'message'],
    );

    // User schema
    $userSchema = JsonSchema::object(
        name: 'User',
        properties: [
            JsonSchema::string(name: 'id')
                ->withDescription('User ID'),
            JsonSchema::string(name: 'username')
                ->withDescription('Username'),
            JsonSchema::string(name: 'email')
                ->withDescription('Email address'),
            JsonSchema::boolean(name: 'isActive')
                ->withDescription('Whether the user is active'),
            JsonSchema::string(name: 'createdAt')
                ->withDescription('Creation timestamp')
                ->withMeta(['format' => 'date-time']),
            JsonSchema::string(name: 'updatedAt')
                ->withDescription('Last update timestamp')
                ->withMeta(['format' => 'date-time']),
        ],
        requiredProperties: ['id', 'username', 'email', 'isActive', 'createdAt', 'updatedAt'],
    );

    // Response schema
    $responseSchema = JsonSchema::object(
        name: 'GetUsersResponse',
        description: 'Response for listing users',
        properties: [
            JsonSchema::boolean(name: 'success')
                ->withDescription('Whether the request was successful'),
            JsonSchema::array(name: 'data')
                ->withDescription('List of users')
                ->withItemSchema($userSchema)
                ->withNullable(true),
            $paginationSchema->withName('pagination')
                ->withDescription('Pagination information')
                ->withNullable(true),
            $errorSchema->withName('error')
                ->withDescription('Error information if success is false')
                ->withNullable(true),
        ],
        requiredProperties: ['success'],
    );

    // Request schema
    $requestSchema = JsonSchema::object(
        name: 'GetUsersRequest',
        description: 'Request parameters for listing users',
        properties: [
            JsonSchema::integer(name: 'page')
                ->withDescription('Page number')
                ->withNullable(true),
            JsonSchema::integer(name: 'perPage')
                ->withDescription('Items per page')
                ->withNullable(true),
            JsonSchema::string(name: 'sortBy')
                ->withDescription('Field to sort by')
                ->withEnumValues(['id', 'username', 'email', 'createdAt', 'updatedAt'])
                ->withNullable(true),
            JsonSchema::string(name: 'sortOrder')
                ->withDescription('Sort order')
                ->withEnumValues(['asc', 'desc'])
                ->withNullable(true),
            JsonSchema::string(name: 'search')
                ->withDescription('Search query')
                ->withNullable(true),
            JsonSchema::object(name: 'filter')
                ->withDescription('Filter criteria')
                ->withNullable(true)
                ->withAdditionalProperties(true),
        ],
    );

    // Create function calls for the endpoint
    $requestFunctionCall = $requestSchema->toFunctionCall(
        functionName: 'getUsers',
        functionDescription: 'List users with pagination and filtering',
    );

    $responseFunctionCall = $responseSchema->toFunctionCall(
        functionName: 'getUsersResponse',
        functionDescription: 'Response for the getUsers endpoint',
    );

    // Check that the schemas are valid
    expect($requestSchema)->toBeInstanceOf(JsonSchema::class)
        ->and($responseSchema)->toBeInstanceOf(JsonSchema::class);

    // Check function call structure
    expect($requestFunctionCall['function']['name'])->toBe('getUsers')
        ->and($responseFunctionCall['function']['name'])->toBe('getUsersResponse');

    // Check parameter/return structure
    $requestParams = $requestFunctionCall['function']['parameters'];
    $responseParams = $responseFunctionCall['function']['parameters'];

    expect($requestParams['properties'])->toHaveCount(6)
        ->and($responseParams['properties'])->toHaveCount(4)
        ->and($responseParams['properties']['data']['items']['properties'])->toHaveCount(6);
});
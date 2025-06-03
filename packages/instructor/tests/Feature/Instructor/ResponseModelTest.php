<?php

use Cognesy\Instructor\Core\ResponseModelFactory;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Instructor\Tests\Examples\ResponseModel\User;
use Cognesy\Instructor\Tests\Examples\ResponseModel\UserWithProvider;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Schema\Utils\ReferenceQueue;
use Cognesy\Utils\Events\EventDispatcher;

dataset('user_response_model', [[[
    'x-php-class' => 'Cognesy\Instructor\Tests\Examples\ResponseModel\User',
    'type' => 'object',
    'properties' => [
        'name' => [
            'type' => 'string'
        ],
        'email' => [
            'type' => 'string'
        ],
    ],
    "required" => [
        0 => 'name',
        1 => 'email',
    ]
]]]);

it('can handle string class name', function() {
    $events = new EventDispatcher('test');
    $responseModelFactory = new ResponseModelFactory(
        new ToolCallBuilder(
            new SchemaFactory(),
            new ReferenceQueue(),
        ),
        new SchemaFactory(),
        new StructuredOutputConfig(),
        $events,
    );
    $responseModel = $responseModelFactory->fromAny(User::class);
    expect($responseModel->instanceClass())->toBe(User::class);
    expect($responseModel->instance())->toBeInstanceOf(User::class);
    expect($responseModel->toJsonSchema())->toBeArray();
    //expect($responseModel->jsonSchema['type'])->toBe('function');
    //expect($responseModel->jsonSchema['function']['name'])->toBe('extract_data');
    //expect($responseModel->jsonSchema['function']['description'])->toBe('Extract data from provided content');
    //expect($responseModel->jsonSchema['function']['parameters'])->toBeArray();
    expect($responseModel->toJsonSchema()['type'])->toBe('object');
    expect($responseModel->toJsonSchema()['properties'])->toBeArray();
    expect($responseModel->toJsonSchema()['properties']['name']['type'])->toBe('string');
    expect($responseModel->toJsonSchema()['properties']['email']['type'])->toBe('string');
    expect($responseModel->toJsonSchema()['required'])->toBeArray();
    expect($responseModel->toJsonSchema()['required'][0])->toBe('name');
    expect($responseModel->toJsonSchema()['required'][1])->toBe('email');
});

it('can handle array schema', function($user) {
    $events = new EventDispatcher('test');
    $responseModelFactory = new ResponseModelFactory(
        new ToolCallBuilder(
            new SchemaFactory(),
            new ReferenceQueue(),
        ),
        new SchemaFactory(),
        new StructuredOutputConfig(),
        $events,
    );
    $responseModel = $responseModelFactory->fromAny($user);
    expect($responseModel->instanceClass())->toBe(User::class);
    expect($responseModel->instance())->toBeInstanceOf(User::class);
    expect($responseModel->toJsonSchema())->toBeArray();
    //expect($responseModel->jsonSchema['type'])->toBe('function');
    //expect($responseModel->jsonSchema['function']['name'])->toBe('extract_data');
    //expect($responseModel->jsonSchema['function']['description'])->toBe('Extract data from provided content');
    //expect($responseModel->jsonSchema['function']['parameters'])->toBeArray();
    expect($responseModel->toJsonSchema()['type'])->toBe('object');
    expect($responseModel->toJsonSchema()['properties'])->toBeArray();
    expect($responseModel->toJsonSchema()['properties']['name']['type'])->toBe('string');
    expect($responseModel->toJsonSchema()['properties']['email']['type'])->toBe('string');
    expect($responseModel->toJsonSchema()['required'])->toBeArray();
    expect($responseModel->toJsonSchema()['required'][0])->toBe('name');
    expect($responseModel->toJsonSchema()['required'][1])->toBe('email');
})->with('user_response_model');

it('can handle schema provider - via instance', function() {
    $events = new EventDispatcher('test');
    $responseModelFactory = new ResponseModelFactory(
        new ToolCallBuilder(
            new SchemaFactory(),
            new ReferenceQueue(),
        ),
        new SchemaFactory(),
        new StructuredOutputConfig(),
        $events,
    );
    $responseModel = $responseModelFactory->fromAny(new UserWithProvider());
    expect($responseModel->instanceClass())->toBe(UserWithProvider::class);
    expect($responseModel->returnedClass())->toBe(User::class);
    expect($responseModel->instance())->toBeInstanceOf(UserWithProvider::class);
    expect($responseModel->toJsonSchema())->toBeArray();
    //expect($responseModel->jsonSchema['type'])->toBe('function');
    //expect($responseModel->jsonSchema['function']['name'])->toBe('extract_data');
    //expect($responseModel->jsonSchema['function']['description'])->toBe('Extract data from provided content');
    //expect($responseModel->jsonSchema['function']['parameters'])->toBeArray();
    expect($responseModel->toJsonSchema()['type'])->toBe('object');
    expect($responseModel->toJsonSchema()['properties'])->toBeArray();
    expect($responseModel->toJsonSchema()['properties']['name']['type'])->toBe('string');
    expect($responseModel->toJsonSchema()['properties']['email']['type'])->toBe('string');
    expect($responseModel->toJsonSchema()['required'])->toBeArray();
    expect($responseModel->toJsonSchema()['required'][0])->toBe('name');
    expect($responseModel->toJsonSchema()['required'][1])->toBe('email');
});

it('can handle schema provider - via class name', function() {
    $events = new EventDispatcher('test');
    $responseModelFactory = new ResponseModelFactory(
        new ToolCallBuilder(
            new SchemaFactory(),
            new ReferenceQueue(),
        ),
        new SchemaFactory(),
        new StructuredOutputConfig(),
        $events,
    );
    $responseModel = $responseModelFactory->fromAny(UserWithProvider::class);
    expect($responseModel->instanceClass())->toBe(UserWithProvider::class);
    expect($responseModel->returnedClass())->toBe(User::class);
    expect($responseModel->instance())->toBeInstanceOf(UserWithProvider::class);
    expect($responseModel->toJsonSchema())->toBeArray();
    //expect($responseModel->jsonSchema['type'])->toBe('function');
    //expect($responseModel->jsonSchema['function']['name'])->toBe('extract_data');
    //expect($responseModel->jsonSchema['function']['description'])->toBe('Extract data from provided content');
    //expect($responseModel->jsonSchema['function']['parameters'])->toBeArray();
    expect($responseModel->toJsonSchema()['type'])->toBe('object');
    expect($responseModel->toJsonSchema()['properties'])->toBeArray();
    expect($responseModel->toJsonSchema()['properties']['name']['type'])->toBe('string');
    expect($responseModel->toJsonSchema()['properties']['email']['type'])->toBe('string');
    expect($responseModel->toJsonSchema()['required'])->toBeArray();
    expect($responseModel->toJsonSchema()['required'][0])->toBe('name');
    expect($responseModel->toJsonSchema()['required'][1])->toBe('email');
});

it('can handle ObjectSchema instance', function() {
    $events = new EventDispatcher('test');
    $responseModelFactory = new ResponseModelFactory(
        new ToolCallBuilder(
            new SchemaFactory(),
            new ReferenceQueue(),
        ),
        new SchemaFactory(),
        new StructuredOutputConfig(),
        $events,
    );
    $schemaFactory = new SchemaFactory();
    $schema = $schemaFactory->schema(User::class);
    $responseModel = $responseModelFactory->fromAny($schema);
    expect($responseModel->instanceClass())->toBe(User::class);
    expect($responseModel->instance())->toBeInstanceOf(User::class);
    expect($responseModel->toJsonSchema())->toBeArray();
    //expect($responseModel->jsonSchema['type'])->toBe('function');
    //expect($responseModel->jsonSchema['function']['name'])->toBe('extract_data');
    //expect($responseModel->jsonSchema['function']['description'])->toBe('Extract data from provided content');
    //expect($responseModel->jsonSchema['function']['parameters'])->toBeArray();
    expect($responseModel->toJsonSchema()['type'])->toBe('object');
    expect($responseModel->toJsonSchema()['properties'])->toBeArray();
    expect($responseModel->toJsonSchema()['properties']['name']['type'])->toBe('string');
    expect($responseModel->toJsonSchema()['properties']['email']['type'])->toBe('string');
    expect($responseModel->toJsonSchema()['required'])->toBeArray();
    expect($responseModel->toJsonSchema()['required'][0])->toBe('name');
    expect($responseModel->toJsonSchema()['required'][1])->toBe('email');
});

it('can handle raw object', function() {
    $events = new EventDispatcher('test');
    $responseModelFactory = new ResponseModelFactory(
        new ToolCallBuilder(
            new SchemaFactory(),
            new ReferenceQueue(),
        ),
        new SchemaFactory(),
        new StructuredOutputConfig(),
        $events,
    );
    $responseModel = $responseModelFactory->fromAny(new User());
    expect($responseModel->instanceClass())->toBe(User::class);
    expect($responseModel->instance())->toBeInstanceOf(User::class);
    expect($responseModel->toJsonSchema())->toBeArray();
    //expect($responseModel->jsonSchema['type'])->toBe('function');
    //expect($responseModel->jsonSchema['function']['name'])->toBe('extract_data');
    //expect($responseModel->jsonSchema['function']['description'])->toBe('Extract data from provided content');
    //expect($responseModel->jsonSchema['function']['parameters'])->toBeArray();
    expect($responseModel->toJsonSchema()['type'])->toBe('object');
    expect($responseModel->toJsonSchema()['properties'])->toBeArray();
    expect($responseModel->toJsonSchema()['properties']['name']['type'])->toBe('string');
    expect($responseModel->toJsonSchema()['properties']['email']['type'])->toBe('string');
    expect($responseModel->toJsonSchema()['required'])->toBeArray();
    expect($responseModel->toJsonSchema()['required'][0])->toBe('name');
    expect($responseModel->toJsonSchema()['required'][1])->toBe('email');
});

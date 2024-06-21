<?php

use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Schema\Utils\ReferenceQueue;
use Tests\Examples\ResponseModel\User;
use Tests\Examples\ResponseModel\UserWithProvider;

it('can handle string class name', function() {
    $responseModelFactory = new ResponseModelFactory(
        new ToolCallBuilder(
            new SchemaFactory(),
            new ReferenceQueue(),
        ),
        new SchemaFactory(),
        new EventDispatcher('test'),
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
    $responseModelFactory = new ResponseModelFactory(
        new ToolCallBuilder(
            new SchemaFactory(),
            new ReferenceQueue(),
        ),
        new SchemaFactory(),
        new EventDispatcher('test'),
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
    $responseModelFactory = new ResponseModelFactory(
        new ToolCallBuilder(
            new SchemaFactory(),
            new ReferenceQueue(),
        ),
        new SchemaFactory(),
        new EventDispatcher('test'),
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
    $responseModelFactory = new ResponseModelFactory(
        new ToolCallBuilder(
            new SchemaFactory(),
            new ReferenceQueue(),
        ),
        new SchemaFactory(),
        new EventDispatcher('test'),
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
    $responseModelFactory = new ResponseModelFactory(
        new ToolCallBuilder(
            new SchemaFactory(),
            new ReferenceQueue(),
        ),
        new SchemaFactory(),
        new EventDispatcher('test'),
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
    $responseModelFactory = new ResponseModelFactory(
        new ToolCallBuilder(
            new SchemaFactory(),
            new ReferenceQueue(),
        ),
        new SchemaFactory(),
        new EventDispatcher('test'),
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


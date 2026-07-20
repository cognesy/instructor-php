<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Tests\Examples\ResponseModel\User;
use Cognesy\Instructor\Tests\Examples\ResponseModel\UserWithProvider;

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
    $config = new StructuredOutputConfig();
    $responseModelFactory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        $events,
    );
    $responseModel = $responseModelFactory->fromAny(User::class);
    expect($responseModel->outputFormat()->targetClass())->toBe(User::class);
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
    $config = new StructuredOutputConfig();
    $responseModelFactory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        $events,
    );
    $responseModel = $responseModelFactory->fromAny($user);
    expect($responseModel->outputFormat()->targetClass())->toBe(User::class);
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
    $config = new StructuredOutputConfig();
    $responseModelFactory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        $events,
    );
    $responseModel = $responseModelFactory->fromAny(new UserWithProvider());
    expect($responseModel->outputFormat()->targetClass())->toBe(User::class);
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
    $config = new StructuredOutputConfig();
    $responseModelFactory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        $events,
    );
    $responseModel = $responseModelFactory->fromAny(UserWithProvider::class);
    expect($responseModel->outputFormat()->targetClass())->toBe(User::class);
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
    $config = new StructuredOutputConfig();
    $responseModelFactory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        $events,
    );
    $schema = (new StructuredOutputSchemaRenderer($config))
        ->schemaFactory()
        ->schema(User::class);
    $responseModel = $responseModelFactory->fromAny($schema);
    expect($responseModel->outputFormat()->targetClass())->toBe(User::class);
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
    $config = new StructuredOutputConfig();
    $responseModelFactory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        $events,
    );
    $responseModel = $responseModelFactory->fromAny(new User());
    expect($responseModel->outputFormat()->targetClass())->toBe(User::class);
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

it('keeps a class-less json schema on the array target', function() {
    $events = new EventDispatcher('test');
    $config = new StructuredOutputConfig();
    $responseModelFactory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        $events,
    );
    $responseModel = $responseModelFactory->fromAny([
        'type' => 'object',
        'name' => 'city',
        'properties' => [
            'name' => ['type' => 'string'],
            'population' => ['type' => 'integer'],
        ],
        'required' => ['name', 'population'],
    ]);

    expect($responseModel->outputFormat()->isArray())->toBeTrue();
    expect($responseModel->schema()->required)->toContain('name');
});

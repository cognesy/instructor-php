<?php

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;
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
    $config = new StructuredOutputConfig();
    $responseModelFactory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
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
    $config = new StructuredOutputConfig();
    $responseModelFactory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
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
    $config = new StructuredOutputConfig();
    $responseModelFactory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
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
    $config = new StructuredOutputConfig();
    $responseModelFactory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
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

it('hydrates dynamic structure from json schema', function() {
    $events = new EventDispatcher('test');
    $config = new StructuredOutputConfig();
    $responseModelFactory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        $events,
    );
    $city = Structure::define('city', [
        Field::string('name')->required(),
        Field::int('population')->required(),
    ]);
    $responseModel = $responseModelFactory->fromAny($city->toJsonSchema());
    $structure = $responseModel->instance();
    expect($structure)->toBeInstanceOf(Structure::class);
    expect($structure->name())->toBe('city');
    expect($structure->field('name')->isRequired())->toBeTrue();
    $structure->fromArray([
        'name' => 'Paris',
        'population' => 2140526,
    ]);
    expect($structure->get('name'))->toBe('Paris');
    expect($structure->get('population'))->toBe(2140526);
});

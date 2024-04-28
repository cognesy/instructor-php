<?php

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Core\ResponseModelFactory;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Tests\Examples\ResponseModel\User;
use Tests\Examples\ResponseModel\UserWithProvider;

it('can handle string class name', function() {
    $responseModel = Configuration::fresh()
        ->get(ResponseModelFactory::class)
        ->fromAny(User::class);
    expect($responseModel->class)->toBe(User::class);
    expect($responseModel->instance)->toBeInstanceOf(User::class);
    expect($responseModel->jsonSchema)->toBeArray();
    //expect($responseModel->jsonSchema['type'])->toBe('function');
    //expect($responseModel->jsonSchema['function']['name'])->toBe('extract_data');
    //expect($responseModel->jsonSchema['function']['description'])->toBe('Extract data from provided content');
    //expect($responseModel->jsonSchema['function']['parameters'])->toBeArray();
    expect($responseModel->jsonSchema['type'])->toBe('object');
    expect($responseModel->jsonSchema['properties'])->toBeArray();
    expect($responseModel->jsonSchema['properties']['name']['type'])->toBe('string');
    expect($responseModel->jsonSchema['properties']['email']['type'])->toBe('string');
    expect($responseModel->jsonSchema['required'])->toBeArray();
    expect($responseModel->jsonSchema['required'][0])->toBe('name');
    expect($responseModel->jsonSchema['required'][1])->toBe('email');
});

it('can handle array schema', function($user) {
    $responseModel = Configuration::fresh()
        ->get(ResponseModelFactory::class)
        ->fromAny($user);
    expect($responseModel->class)->toBe(User::class);
    expect($responseModel->instance)->toBeInstanceOf(User::class);
    expect($responseModel->jsonSchema)->toBeArray();
    //expect($responseModel->jsonSchema['type'])->toBe('function');
    //expect($responseModel->jsonSchema['function']['name'])->toBe('extract_data');
    //expect($responseModel->jsonSchema['function']['description'])->toBe('Extract data from provided content');
    //expect($responseModel->jsonSchema['function']['parameters'])->toBeArray();
    expect($responseModel->jsonSchema['type'])->toBe('object');
    expect($responseModel->jsonSchema['properties'])->toBeArray();
    expect($responseModel->jsonSchema['properties']['name']['type'])->toBe('string');
    expect($responseModel->jsonSchema['properties']['email']['type'])->toBe('string');
    expect($responseModel->jsonSchema['required'])->toBeArray();
    expect($responseModel->jsonSchema['required'][0])->toBe('name');
    expect($responseModel->jsonSchema['required'][1])->toBe('email');
})->with('user_response_model');

it('can handle schema provider - via instance', function() {
    $responseModel = Configuration::fresh()
        ->get(ResponseModelFactory::class)
        ->fromAny(new UserWithProvider());
    expect($responseModel->class)->toBe(UserWithProvider::class);
    expect($responseModel->instance)->toBeInstanceOf(UserWithProvider::class);
    expect($responseModel->jsonSchema)->toBeArray();
    //expect($responseModel->jsonSchema['type'])->toBe('function');
    //expect($responseModel->jsonSchema['function']['name'])->toBe('extract_data');
    //expect($responseModel->jsonSchema['function']['description'])->toBe('Extract data from provided content');
    //expect($responseModel->jsonSchema['function']['parameters'])->toBeArray();
    expect($responseModel->jsonSchema['type'])->toBe('object');
    expect($responseModel->jsonSchema['properties'])->toBeArray();
    expect($responseModel->jsonSchema['properties']['name']['type'])->toBe('string');
    expect($responseModel->jsonSchema['properties']['email']['type'])->toBe('string');
    expect($responseModel->jsonSchema['required'])->toBeArray();
    expect($responseModel->jsonSchema['required'][0])->toBe('name');
    expect($responseModel->jsonSchema['required'][1])->toBe('email');
});

it('can handle schema provider - via class name', function() {
    $responseModel = Configuration::fresh()
        ->get(ResponseModelFactory::class)
        ->fromAny(UserWithProvider::class);
    expect($responseModel->class)->toBe(UserWithProvider::class);
    expect($responseModel->instance)->toBeInstanceOf(UserWithProvider::class);
    expect($responseModel->jsonSchema)->toBeArray();
    //expect($responseModel->jsonSchema['type'])->toBe('function');
    //expect($responseModel->jsonSchema['function']['name'])->toBe('extract_data');
    //expect($responseModel->jsonSchema['function']['description'])->toBe('Extract data from provided content');
    //expect($responseModel->jsonSchema['function']['parameters'])->toBeArray();
    expect($responseModel->jsonSchema['type'])->toBe('object');
    expect($responseModel->jsonSchema['properties'])->toBeArray();
    expect($responseModel->jsonSchema['properties']['name']['type'])->toBe('string');
    expect($responseModel->jsonSchema['properties']['email']['type'])->toBe('string');
    expect($responseModel->jsonSchema['required'])->toBeArray();
    expect($responseModel->jsonSchema['required'][0])->toBe('name');
    expect($responseModel->jsonSchema['required'][1])->toBe('email');
});

it('can handle ObjectSchema instance', function() {
    $schema = Configuration::fresh()
        ->get(SchemaFactory::class)
        ->schema(User::class);
    $responseModel = Configuration::fresh()
        ->get(ResponseModelFactory::class)
        ->fromAny($schema);
    expect($responseModel->class)->toBe(User::class);
    expect($responseModel->instance)->toBeInstanceOf(User::class);
    expect($responseModel->jsonSchema)->toBeArray();
    //expect($responseModel->jsonSchema['type'])->toBe('function');
    //expect($responseModel->jsonSchema['function']['name'])->toBe('extract_data');
    //expect($responseModel->jsonSchema['function']['description'])->toBe('Extract data from provided content');
    //expect($responseModel->jsonSchema['function']['parameters'])->toBeArray();
    expect($responseModel->jsonSchema['type'])->toBe('object');
    expect($responseModel->jsonSchema['properties'])->toBeArray();
    expect($responseModel->jsonSchema['properties']['name']['type'])->toBe('string');
    expect($responseModel->jsonSchema['properties']['email']['type'])->toBe('string');
    expect($responseModel->jsonSchema['required'])->toBeArray();
    expect($responseModel->jsonSchema['required'][0])->toBe('name');
    expect($responseModel->jsonSchema['required'][1])->toBe('email');
});

it('can handle raw object', function() {
    $responseModel = Configuration::fresh()
        ->get(ResponseModelFactory::class)
        ->fromAny(new User());
    expect($responseModel->class)->toBe(User::class);
    expect($responseModel->instance)->toBeInstanceOf(User::class);
    expect($responseModel->jsonSchema)->toBeArray();
    //expect($responseModel->jsonSchema['type'])->toBe('function');
    //expect($responseModel->jsonSchema['function']['name'])->toBe('extract_data');
    //expect($responseModel->jsonSchema['function']['description'])->toBe('Extract data from provided content');
    //expect($responseModel->jsonSchema['function']['parameters'])->toBeArray();
    expect($responseModel->jsonSchema['type'])->toBe('object');
    expect($responseModel->jsonSchema['properties'])->toBeArray();
    expect($responseModel->jsonSchema['properties']['name']['type'])->toBe('string');
    expect($responseModel->jsonSchema['properties']['email']['type'])->toBe('string');
    expect($responseModel->jsonSchema['required'])->toBeArray();
    expect($responseModel->jsonSchema['required'][0])->toBe('name');
    expect($responseModel->jsonSchema['required'][1])->toBe('email');
});
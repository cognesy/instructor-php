<?php

use Cognesy\Instructor\ResponseModel;
use Cognesy\Instructor\Schema\PropertyInfoBased\Factories\SchemaFactory;
use Tests\Examples\ResponseModel\User;
use Tests\Examples\ResponseModel\UserWithProvider;


it('can handle string class name', function() {
    $responseModel = new ResponseModel(User::class);
    expect($responseModel->class)->toBe(User::class);
    expect($responseModel->instance)->toBeInstanceOf(User::class);
    expect($responseModel->functionCall)->toBeArray();
    expect($responseModel->functionCall['type'])->toBe('function');
    expect($responseModel->functionCall['function']['name'])->toBe('extract_data');
    expect($responseModel->functionCall['function']['description'])->toBe('Extract data from provided content');
    expect($responseModel->functionCall['function']['parameters'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['type'])->toBe('object');
    expect($responseModel->functionCall['function']['parameters']['properties'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['properties']['name']['type'])->toBe('string');
    expect($responseModel->functionCall['function']['parameters']['properties']['email']['type'])->toBe('string');
    expect($responseModel->functionCall['function']['parameters']['required'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['required'][0])->toBe('name');
    expect($responseModel->functionCall['function']['parameters']['required'][1])->toBe('email');
});

it('can handle array schema', function($user) {
    $responseModel = new ResponseModel($user);
    expect($responseModel->class)->toBe(User::class);
    expect($responseModel->instance)->toBeInstanceOf(User::class);
    expect($responseModel->functionCall)->toBeArray();
    expect($responseModel->functionCall['type'])->toBe('function');
    expect($responseModel->functionCall['function']['name'])->toBe('extract_data');
    expect($responseModel->functionCall['function']['description'])->toBe('Extract data from provided content');
    expect($responseModel->functionCall['function']['parameters'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['type'])->toBe('object');
    expect($responseModel->functionCall['function']['parameters']['properties'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['properties']['name']['type'])->toBe('string');
    expect($responseModel->functionCall['function']['parameters']['properties']['email']['type'])->toBe('string');
    expect($responseModel->functionCall['function']['parameters']['required'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['required'][0])->toBe('name');
    expect($responseModel->functionCall['function']['parameters']['required'][1])->toBe('email');
})->with('user_response_model');

it('can handle schema provider - via instance', function() {
    $responseModel = new ResponseModel(new UserWithProvider());
    expect($responseModel->class)->toBe(UserWithProvider::class);
    expect($responseModel->instance)->toBeInstanceOf(UserWithProvider::class);
    expect($responseModel->functionCall)->toBeArray();
    expect($responseModel->functionCall['type'])->toBe('function');
    expect($responseModel->functionCall['function']['name'])->toBe('extract_data');
    expect($responseModel->functionCall['function']['description'])->toBe('Extract data from provided content');
    expect($responseModel->functionCall['function']['parameters'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['type'])->toBe('object');
    expect($responseModel->functionCall['function']['parameters']['properties'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['properties']['name']['type'])->toBe('string');
    expect($responseModel->functionCall['function']['parameters']['properties']['email']['type'])->toBe('string');
    expect($responseModel->functionCall['function']['parameters']['required'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['required'][0])->toBe('name');
    expect($responseModel->functionCall['function']['parameters']['required'][1])->toBe('email');
});

it('can handle schema provider - via class name', function() {
    $responseModel = new ResponseModel(UserWithProvider::class);
    expect($responseModel->class)->toBe(UserWithProvider::class);
    expect($responseModel->instance)->toBeInstanceOf(UserWithProvider::class);
    expect($responseModel->functionCall)->toBeArray();
    expect($responseModel->functionCall['type'])->toBe('function');
    expect($responseModel->functionCall['function']['name'])->toBe('extract_data');
    expect($responseModel->functionCall['function']['description'])->toBe('Extract data from provided content');
    expect($responseModel->functionCall['function']['parameters'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['type'])->toBe('object');
    expect($responseModel->functionCall['function']['parameters']['properties'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['properties']['name']['type'])->toBe('string');
    expect($responseModel->functionCall['function']['parameters']['properties']['email']['type'])->toBe('string');
    expect($responseModel->functionCall['function']['parameters']['required'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['required'][0])->toBe('name');
    expect($responseModel->functionCall['function']['parameters']['required'][1])->toBe('email');
});

it('can handle raw ObjectSchema instance', function() {
    $schema = (new SchemaFactory)->schema(User::class);
    $responseModel = new ResponseModel($schema);
    expect($responseModel->class)->toBe(User::class);
    expect($responseModel->instance)->toBeInstanceOf(User::class);
    expect($responseModel->functionCall)->toBeArray();
    expect($responseModel->functionCall['type'])->toBe('function');
    expect($responseModel->functionCall['function']['name'])->toBe('extract_data');
    expect($responseModel->functionCall['function']['description'])->toBe('Extract data from provided content');
    expect($responseModel->functionCall['function']['parameters'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['type'])->toBe('object');
    expect($responseModel->functionCall['function']['parameters']['properties'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['properties']['name']['type'])->toBe('string');
    expect($responseModel->functionCall['function']['parameters']['properties']['email']['type'])->toBe('string');
    expect($responseModel->functionCall['function']['parameters']['required'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['required'][0])->toBe('name');
    expect($responseModel->functionCall['function']['parameters']['required'][1])->toBe('email');
});

it('can handle raw object', function() {
    $responseModel = new ResponseModel(new User());
    expect($responseModel->class)->toBe(User::class);
    expect($responseModel->instance)->toBeInstanceOf(User::class);
    expect($responseModel->functionCall)->toBeArray();
    expect($responseModel->functionCall['type'])->toBe('function');
    expect($responseModel->functionCall['function']['name'])->toBe('extract_data');
    expect($responseModel->functionCall['function']['description'])->toBe('Extract data from provided content');
    expect($responseModel->functionCall['function']['parameters'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['type'])->toBe('object');
    expect($responseModel->functionCall['function']['parameters']['properties'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['properties']['name']['type'])->toBe('string');
    expect($responseModel->functionCall['function']['parameters']['properties']['email']['type'])->toBe('string');
    expect($responseModel->functionCall['function']['parameters']['required'])->toBeArray();
    expect($responseModel->functionCall['function']['parameters']['required'][0])->toBe('name');
    expect($responseModel->functionCall['function']['parameters']['required'][1])->toBe('email');
});
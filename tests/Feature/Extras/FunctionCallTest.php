<?php
namespace Tests\Feature\Extras;

use Cognesy\Instructor\Extras\FunctionCall\FunctionCall;
use Tests\Examples\Call\TestClass;
require_once __DIR__.'/../../Examples/Call/test_functions.php';
use function Tests\Examples\Call\testFunction;
use function Tests\Examples\Call\variadicFunction;

it('can process function by name', function () {
    $call = FunctionCall::fromFunctionName('Tests\Examples\Call\testFunction');
    expect($call->getName())->toBe('testFunction');
    expect($call->getDescription())->toBe('Test function description');
});

it('can process function by callable', function () {
    $call = FunctionCall::fromCallable(testFunction(...));
    expect($call->getName())->toBe('testFunction');
    expect($call->getDescription())->toBe('Test function description');
});

it('can process class method by name', function () {
    $call = FunctionCall::fromMethodName(TestClass::class, 'testMethod');
    expect($call->getName())->toBe('testMethod');
    expect($call->getDescription())->toBe('Test method description');
});

it('can process class method by callable', function () {
    $class = new TestClass();
    $call = FunctionCall::fromCallable($class->testMethod(...));
    expect($call->getName())->toBe('testMethod');
    expect($call->getDescription())->toBe('Test method description');
});

it('can get arguments from function', function () {
    $call = FunctionCall::fromCallable(testFunction(...));
    $arguments = $call->getArgumentNames();
    expect($arguments)->toBe([
        'intParam',
        'stringParam',
        'boolParam',
        'objectParam',
    ]);
});

it('can get arguments from method', function () {
    $call = FunctionCall::fromCallable((new TestClass())->testMethod(...));
    $arguments = $call->getArgumentNames();
    expect($arguments)->toBe([
        'intParam',
        'stringParam',
        'boolParam',
        'objectParam',
    ]);
});

it('it can handle variadic args', function () {
    $call = FunctionCall::fromCallable(variadicFunction(...));
    $arguments = $call->getArgumentNames();
    expect($arguments)->toBe(['objectParams']);
    expect($call->getField('objectParams')->typeDetails()->type)->toBe('collection');
    expect($call->getField('objectParams')->typeDetails()->nestedType->type)->toBe('object');
    expect($call->getField('objectParams')->typeDetails()->nestedType->class)->toBe('Tests\Examples\Call\TestClass');
});

it('can deserialize from JSON', function () {
    $json = '{
        "intParam": 1,
        "stringParam": "string",
        "boolParam": true,
        "objectParam": {
            "intField": 1,
            "stringField": "string",
            "boolField": true
        }
    }';
    $call = FunctionCall::fromCallable(testFunction(...));
    $call->fromJson($json);
    $args = $call->transform();
    expect($args['intParam'])->toBe(1);
    expect($args['stringParam'])->toBe('string');
    expect($args['boolParam'])->toBe(true);
    expect($args['objectParam'])->toBeInstanceOf(TestClass::class);
    expect($args['objectParam']->intField)->toBe(1);
    expect($args['objectParam']->stringField)->toBe('string');
    expect($args['objectParam']->boolField)->toBe(true);
});

it('can provide JSON schema of function call', function () {
    $call = FunctionCall::fromCallable(testFunction(...));
    $jsonSchema = $call->toJsonSchema();
    expect($jsonSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'intParam' => ['type' => 'integer'],
            'stringParam' => ['type' => 'string'],
            'boolParam' => ['type' => 'boolean'],
            'objectParam' => [
                'type' => 'object',
                'title' => 'objectParam',
                'properties' => [
                    'intField' => ['type' => 'integer'],
                    'stringField' => ['type' => 'string'],
                    'boolField' => ['type' => 'boolean'],
                ],
                'required' => ['intField', 'stringField', 'boolField'],
                '$comment' => 'Tests\Examples\Call\TestClass'
            ],
        ],
        'required' => ['intParam', 'stringParam', 'boolParam', 'objectParam'],
        '$comment' => 'Cognesy\Instructor\Extras\Structure\Structure',
    ]);
});

it('can provide OpenAI tool call format for function', function () {
    $call = FunctionCall::fromCallable(testFunction(...));
    $toolCall = $call->toToolCall();
    expect($toolCall)->toBe([
        'type' => 'function',
        'function' => [
            'name' => 'testFunction',
            'description' => 'Test function description',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'intParam' => ['type' => 'integer'],
                    'stringParam' => ['type' => 'string'],
                    'boolParam' => ['type' => 'boolean'],
                    'objectParam' => [
                        'type' => 'object',
                        'title' => 'objectParam',
                        'properties' => [
                            'intField' => ['type' => 'integer'],
                            'stringField' => ['type' => 'string'],
                            'boolField' => ['type' => 'boolean'],
                        ],
                        'required' => ['intField', 'stringField', 'boolField'],
                        '$comment' => 'Tests\Examples\Call\TestClass'
                    ],
                ],
                'required' => ['intParam', 'stringParam', 'boolParam', 'objectParam'],
                '$comment' => 'Cognesy\Instructor\Extras\Structure\Structure',
            ],
        ],
    ]);
});

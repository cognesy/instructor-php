<?php

use Cognesy\Addons\FunctionCall\FunctionCall;
use Cognesy\Addons\Tests\Examples\Call\TestClass;
use function Cognesy\Addons\Tests\Examples\Call\testFunction;
use function Cognesy\Addons\Tests\Examples\Call\testFunctionWithDefault;
use function Cognesy\Addons\Tests\Examples\Call\variadicFunction;

it('can process function by name', function () {
    $call = FunctionCall::fromFunctionName('Cognesy\Addons\Tests\Examples\Call\testFunction');
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
    expect($call->getArgumentInfo('objectParams')->typeDetails()->type)->toBe('collection');
    expect($call->getArgumentInfo('objectParams')->typeDetails()->nestedType->type)->toBe('object');
    expect($call->getArgumentInfo('objectParams')->typeDetails()->nestedType->class)->toBe('Cognesy\Addons\Tests\Examples\Call\TestClass');
});

it('it can handle default args', function () {
    $call = FunctionCall::fromCallable(testFunctionWithDefault(...));
    $arguments = $call->getArgumentNames();
    expect($arguments)->toBe([
        'objectParam',
        'intParam',
        'stringParam',
        'boolParam',
    ]);
    expect($call->getArgumentInfo('objectParam')->defaultValue())->toBe(null);
    expect($call->getArgumentInfo('intParam')->defaultValue())->toBe(1);
    expect($call->getArgumentInfo('stringParam')->defaultValue())->toBe('default');
    expect($call->getArgumentInfo('boolParam')->defaultValue())->toBe(true);
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
                'x-title' => 'objectParam',
                'properties' => [
                    'intField' => ['type' => 'integer'],
                    'stringField' => ['type' => 'string'],
                    'boolField' => ['type' => 'boolean'],
                ],
                'required' => ['intField', 'stringField', 'boolField'],
                'x-php-class' => 'Cognesy\Addons\Tests\Examples\Call\TestClass',
                'additionalProperties' => false,
            ],
        ],
        'required' => ['intParam', 'stringParam', 'boolParam', 'objectParam'],
        'x-php-class' => 'Cognesy\Instructor\Extras\Structure\Structure',
        'additionalProperties' => false,
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
                        'x-title' => 'objectParam',
                        'properties' => [
                            'intField' => ['type' => 'integer'],
                            'stringField' => ['type' => 'string'],
                            'boolField' => ['type' => 'boolean'],
                        ],
                        'required' => ['intField', 'stringField', 'boolField'],
                        'x-php-class' => 'Cognesy\Addons\Tests\Examples\Call\TestClass',
                        'additionalProperties' => false,
                    ],
                ],
                'required' => ['intParam', 'stringParam', 'boolParam', 'objectParam'],
                'x-php-class' => 'Cognesy\Instructor\Extras\Structure\Structure',
                'additionalProperties' => false,
            ],
        ],
    ]);
});

<?php

use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\JsonSchemaRenderer;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\Tests\Examples\ClassInfo\TestClassA;

it('creates a schema from a class name', function () {
    $factory = new SchemaFactory();
    /** @var ObjectSchema $schema */
    $schema = $factory->schema(TestClassA::class);

    expect($schema)->toBeInstanceOf(ObjectSchema::class);
    expect($schema->getPropertyNames())->toBeArray();
    expect($schema->getPropertyNames())->toBe([
        'mixedProperty',
        'attributeMixedProperty',
        'nonNullableIntProperty',
        'explicitMixedProperty',
        'nullableIntProperty',
        'readOnlyStringProperty'
    ]);
});

it('creates a schema from a class name with object references', function () {
    $factory = new SchemaFactory(useObjectReferences: true);
    /** @var ObjectSchema $schema */
    $schema = $factory->schema(TestClassA::class);
    $json = (new JsonSchemaRenderer)->toArray($schema);

    expect($json)->toBeArray();
    $expected = [
        'type' => 'object',
        'x-title' => 'TestClassA',
        'description' => 'Class description',
        'properties' => [
            'mixedProperty' => [
                //'type' => ['null', 'boolean', 'object', 'array', 'number', 'string'],
                'description' => 'Property description'
            ],
            'attributeMixedProperty' => [
                //'type' => 'string',
                'description' => 'Attribute description'
            ],
            'nonNullableIntProperty' => [
                'type' => 'integer'
            ],
            'explicitMixedProperty' => [
                //'type' => 'string'
            ],
            'nullableIntProperty' => [
                'type' => 'integer'
            ],
            'readOnlyStringProperty' => [
                'type' => 'string'
            ],
        ],
        'required' => [
            //'mixedProperty',
            //'attributeMixedProperty',
            'nonNullableIntProperty',
            //'explicitMixedProperty',
            'readOnlyStringProperty',
        ],
        'x-php-class' => 'Cognesy\Schema\Tests\Examples\ClassInfo\TestClassA',
        'additionalProperties' => false,
    ];
    expect($json)->toBe($expected);
});

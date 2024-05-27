<?php

use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Visitors\SchemaToArray;
use Tests\Examples\ClassInfo\TestClassA;

it('creates a schema from a class name', function () {
    $factory = new SchemaFactory();
    /** @var ObjectSchema $schema */
    $schema = $factory->schema(TestClassA::class);

    expect($schema)->toBeInstanceOf(ObjectSchema::class);
    expect($schema->getPropertyNames())->toBeArray();
    expect($schema->getPropertyNames())->toBe(['testProperty', 'attributeProperty', 'nonNullableProperty', 'publicProperty', 'nullableProperty', 'readOnlyProperty']);
});

it('creates a schema from a class name with object references', function () {
    $factory = new SchemaFactory(true);
    /** @var ObjectSchema $schema */
    $schema = $factory->schema(TestClassA::class);
    $array = (new SchemaToArray)->toArray($schema);
    expect($array)->toBeArray();
    $expected = [
        'type' => 'object',
        'title' => 'TestClassA',
        'description' => 'Class description',
        'properties' => [
            'testProperty' => [
                'type' => 'string',
                'description' => 'Property description'
            ],
            'attributeProperty' => [
                'type' => 'string',
                'description' => 'Attribute description'
            ],
            'nonNullableProperty' => [
                'type' => 'integer'
            ],
            'publicProperty' => [
                'type' => 'string'
            ],
            'nullableProperty' => [
                'type' => 'integer'
            ],
            'readOnlyProperty' => [
                'type' => 'string'
            ],
        ],
        'required' => [
            'testProperty',
            'attributeProperty',
            'nonNullableProperty',
            'publicProperty',
            'readOnlyProperty',
        ],
        '$comment' => 'Tests\Examples\ClassInfo\TestClassA',
    ];
    expect($array)->toBe($expected);
});

<?php

use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Tests\Examples\ClassInfo\TestClassA;
use Cognesy\Schema\Visitors\SchemaToJsonSchema;

it('creates a schema from a class name', function () {
    $factory = new SchemaFactory();
    /** @var ObjectSchema $schema */
    $schema = $factory->schema(TestClassA::class);

    expect($schema)->toBeInstanceOf(ObjectSchema::class);
    expect($schema->getPropertyNames())->toBeArray();
    expect($schema->getPropertyNames())->toBe(['testProperty', 'attributeProperty', 'nonNullableProperty', 'publicProperty', 'nullableProperty', 'readOnlyProperty']);
});

it('creates a schema from a class name with object references', function () {
    $factory = new SchemaFactory(useObjectReferences: true);
    /** @var ObjectSchema $schema */
    $schema = $factory->schema(TestClassA::class);
    $json = (new SchemaToJsonSchema)->toArray($schema);
    expect($json)->toBeArray();
    $expected = [
        'type' => 'object',
        'x-title' => 'TestClassA',
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
        'x-php-class' => 'Cognesy\Schema\Tests\Examples\ClassInfo\TestClassA',
        'additionalProperties' => false,
    ];
    expect($json)->toBe($expected);
});

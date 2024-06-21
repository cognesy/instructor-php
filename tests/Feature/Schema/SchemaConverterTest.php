<?php
namespace Tests;

use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Factories\SchemaConverter;

it('creates Schema object from JSON Schema array - scalar props', function ($jsonSchema) {
    $schema = (new SchemaConverter)->fromJsonSchema($jsonSchema, '', '');
    expect($schema)->toBeInstanceOf(ObjectSchema::class);

    expect($schema->properties['stringProperty']->name)->toBe('stringProperty');
    expect($schema->properties['stringProperty']->description)->toBe('String property');
    expect($schema->properties['stringProperty']->typeDetails->type)->toBe('string');
    expect($schema->properties['integerProperty']->name)->toBe('integerProperty');
    expect($schema->properties['integerProperty']->description)->toBe('Integer property');
    expect($schema->properties['integerProperty']->typeDetails->type)->toBe('int');
    expect($schema->properties['boolProperty']->name)->toBe('boolProperty');
    expect($schema->properties['boolProperty']->description)->toBe('Boolean property');
    expect($schema->properties['boolProperty']->typeDetails->type)->toBe('bool');
    expect($schema->properties['floatProperty']->name)->toBe('floatProperty');
    expect($schema->properties['floatProperty']->description)->toBe('Float property');
    expect($schema->properties['floatProperty']->typeDetails->type)->toBe('float');

    expect($schema->required)->toBe(['stringProperty', 'integerProperty', 'boolProperty', 'floatProperty', 'enumProperty', 'objectProperty', 'arrayProperty', 'arrayObjectProperty', 'arrayEnumProperty']);
})->with('schema_converter_json');

it('creates Schema object from JSON Schema array - enum props', function ($jsonSchema) {
    $schema = (new SchemaConverter)->fromJsonSchema($jsonSchema, '', '');
    expect($schema)->toBeInstanceOf(ObjectSchema::class);

    expect($schema->properties['enumProperty']->name)->toBe('enumProperty');
    expect($schema->properties['enumProperty']->description)->toBe('Enum property');
    expect($schema->properties['enumProperty']->typeDetails->type)->toBe('enum');
    expect($schema->properties['enumProperty']->typeDetails->class)->toBe('Tests\Examples\SchemaConverter\TestEnum');
    expect($schema->properties['enumProperty']->typeDetails->enumType)->toBe('string');
    expect($schema->properties['enumProperty']->typeDetails->enumValues)->toBe(['one', 'two', 'three']);

    expect($schema->required)->toBe(['stringProperty', 'integerProperty', 'boolProperty', 'floatProperty', 'enumProperty', 'objectProperty', 'arrayProperty', 'arrayObjectProperty', 'arrayEnumProperty']);
})->with('schema_converter_json');


it('creates Schema object from JSON Schema array - object props', function ($jsonSchema) {
    $schema = (new SchemaConverter)->fromJsonSchema($jsonSchema, '', '');
    expect($schema)->toBeInstanceOf(ObjectSchema::class);

    expect($schema->properties['objectProperty']->name)->toBe('objectProperty');
    expect($schema->properties['objectProperty']->description)->toBe('Object property');
    expect($schema->properties['objectProperty']->typeDetails->type)->toBe('object');
    expect($schema->properties['objectProperty']->typeDetails->class)->toBe('Tests\Examples\SchemaConverter\TestNestedObject');
    expect($schema->properties['objectProperty']->properties['nestedStringProperty']->name)->toBe('nestedStringProperty');
    expect($schema->properties['objectProperty']->properties['nestedStringProperty']->typeDetails->type)->toBe('string');
    expect($schema->properties['objectProperty']->properties['nestedObjectProperty']->name)->toBe('nestedObjectProperty');
    expect($schema->properties['objectProperty']->properties['nestedObjectProperty']->typeDetails->type)->toBe('object');
    expect($schema->properties['objectProperty']->properties['nestedObjectProperty']->typeDetails->class)->toBe('Tests\Examples\SchemaConverter\TestDoubleNestedObject');
    expect($schema->properties['objectProperty']->properties['nestedObjectProperty']->properties['nestedNestedStringProperty']->name)->toBe('nestedNestedStringProperty');
    expect($schema->properties['objectProperty']->properties['nestedObjectProperty']->properties['nestedNestedStringProperty']->typeDetails->type)->toBe('string');

    expect($schema->required)->toBe(['stringProperty', 'integerProperty', 'boolProperty', 'floatProperty', 'enumProperty', 'objectProperty', 'arrayProperty', 'arrayObjectProperty', 'arrayEnumProperty']);
})->with('schema_converter_json');


it('creates Schema object from JSON Schema array - array props', function ($jsonSchema) {
    $schema = (new SchemaConverter)->fromJsonSchema($jsonSchema, '', '');
    expect($schema)->toBeInstanceOf(ObjectSchema::class);

    expect($schema->properties['arrayProperty']->name)->toBe('arrayProperty');
    expect($schema->properties['arrayProperty']->description)->toBe('Array property');
    expect($schema->properties['arrayProperty']->typeDetails->type)->toBe('collection');
    expect($schema->properties['arrayProperty']->nestedItemSchema->typeDetails->type)->toBe('string');
    expect($schema->properties['arrayProperty']->nestedItemSchema->typeDetails->class)->toBe(null);
    expect($schema->properties['arrayObjectProperty']->name)->toBe('arrayObjectProperty');
    expect($schema->properties['arrayObjectProperty']->description)->toBe('Array of objects property');
    expect($schema->properties['arrayObjectProperty']->typeDetails->type)->toBe('collection');
    expect($schema->properties['arrayObjectProperty']->nestedItemSchema->typeDetails->type)->toBe('object');
    expect($schema->properties['arrayObjectProperty']->nestedItemSchema->typeDetails->class)->toBe('Tests\Examples\SchemaConverter\TestNestedObject');
    expect($schema->properties['arrayObjectProperty']->nestedItemSchema->properties['nestedStringProperty']->name)->toBe('nestedStringProperty');
    expect($schema->properties['arrayObjectProperty']->nestedItemSchema->properties['nestedStringProperty']->typeDetails->type)->toBe('string');
    expect($schema->properties['arrayEnumProperty']->name)->toBe('arrayEnumProperty');
    expect($schema->properties['arrayEnumProperty']->description)->toBe('Array of enum property');
    expect($schema->properties['arrayEnumProperty']->typeDetails->type)->toBe('collection');
    expect($schema->properties['arrayEnumProperty']->nestedItemSchema->typeDetails->type)->toBe('enum');
    expect($schema->properties['arrayEnumProperty']->nestedItemSchema->typeDetails->class)->toBe('Tests\Examples\SchemaConverter\TestEnum');
    expect($schema->properties['arrayEnumProperty']->nestedItemSchema->typeDetails->enumType)->toBe('string');
    expect($schema->properties['arrayEnumProperty']->nestedItemSchema->typeDetails->enumValues)->toBe(['one', 'two', 'three']);

    expect($schema->required)->toBe(['stringProperty', 'integerProperty', 'boolProperty', 'floatProperty', 'enumProperty', 'objectProperty', 'arrayProperty', 'arrayObjectProperty', 'arrayEnumProperty']);
})->with('schema_converter_json');

it('throws exception when object schema has empty properties array', function () {
    $jsonSchema = [
        'type' => 'object',
        'properties' => [],
    ];
    (new SchemaConverter)->fromJsonSchema($jsonSchema, '', '');
})->throws(\Exception::class, 'Object must have at least one property');

it('throws exception when array schema is missing items field', function () {
    $jsonSchema = [
        'type' => 'object',
        'properties' => [
            'arrayProperty' => [
                'type' => 'array',
            ],
        ],
    ];
    (new SchemaConverter)->fromJsonSchema($jsonSchema, '', '');
})->throws(\Exception::class, 'Array must have items field defining the nested type');

it('throws exception for invalid enum type', function () {
    $jsonSchema = [
        'type' => 'object',
        'properties' => [
            'arrayProperty' => [
                'type' => 'array',
                'items' => [
                    'type' => 'enum',
                    'enum' => ['one', 'two', 'three'],
                    '$comment' => 'Tests\Examples\SchemaConverter\TestEnum',
                ],
            ],
        ],
    ];
    (new SchemaConverter)->fromJsonSchema($jsonSchema, '', '');
})->throws(\Exception::class, 'Nested enum type must be either string or int');

it('throws exception when $comment is missing for enum', function () {
    $jsonSchema = [
        'type' => 'object',
        'properties' => [
            'arrayProperty' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['one', 'two', 'three'],
                ],
            ],
        ],
    ];
    (new SchemaConverter)->fromJsonSchema($jsonSchema, '', '');
})->throws(\Exception::class, 'Nested enum type needs $comment field');

it('throws exception when $comment is missing for object', function () {
    $jsonSchema = [
        'type' => 'object',
        'properties' => [
            'objectProperty' => [
                'type' => 'object',
                'properties' => [
                    'nestedProperty' => ['type' => 'string'],
                ]
            ],
        ],
    ];
    (new SchemaConverter)->fromJsonSchema($jsonSchema, '', '');
})->throws(\Exception::class, 'Object must have $comment field with the target class name');

it('throws exception for invalid type in nested schema', function () {
    $jsonSchema = [
        'type' => 'object',
        'properties' => [
            'arrayProperty' => [
                'type' => 'array',
                'items' => [
                    'type' => 'invalidType',
                ],
            ],
        ],
    ];
    (new SchemaConverter)->fromJsonSchema($jsonSchema, '', '');
})->throws(\Exception::class, 'Unknown type: invalidType');

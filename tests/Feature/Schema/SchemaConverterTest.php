<?php
namespace Tests;

use Cognesy\Instructor\Features\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Features\Schema\Factories\JsonSchemaToSchema;

it('creates Schema object from JSON Schema array - required fields', function ($jsonSchema) {
    $schema = (new JsonSchemaToSchema)->fromJsonSchema($jsonSchema, '', '');
    expect($schema)->toBeInstanceOf(ObjectSchema::class);
    expect($schema->required)->toBe([
        'stringProperty',
        'integerProperty',
        'boolProperty',
        'floatProperty',
        'enumProperty',
        'objectProperty',
        'arrayProperty',
        'collectionProperty',
        'collectionObjectProperty',
        'collectionEnumProperty'
    ]);
})->with('schema_converter_json');

it('creates Schema object from JSON Schema array - scalar props', function ($jsonSchema) {
    $schema = (new JsonSchemaToSchema)->fromJsonSchema($jsonSchema, '', '');
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
})->with('schema_converter_json');

it('creates Schema object from JSON Schema array - enum props', function ($jsonSchema) {
    $schema = (new JsonSchemaToSchema)->fromJsonSchema($jsonSchema, '', '');
    expect($schema)->toBeInstanceOf(ObjectSchema::class);

    expect($schema->properties['enumProperty']->name)->toBe('enumProperty');
    expect($schema->properties['enumProperty']->description)->toBe('Enum property');
    expect($schema->properties['enumProperty']->typeDetails->type)->toBe('enum');
    expect($schema->properties['enumProperty']->typeDetails->class)->toBe('Tests\Examples\SchemaConverter\TestEnum');
    expect($schema->properties['enumProperty']->typeDetails->enumType)->toBe('string');
    expect($schema->properties['enumProperty']->typeDetails->enumValues)->toBe(['one', 'two', 'three']);
})->with('schema_converter_json');


it('creates Schema object from JSON Schema array - object props', function ($jsonSchema) {
    $schema = (new JsonSchemaToSchema)->fromJsonSchema($jsonSchema, '', '');
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

})->with('schema_converter_json');


it('creates Schema object from JSON Schema array - array props', function ($jsonSchema) {
    $schema = (new JsonSchemaToSchema)->fromJsonSchema($jsonSchema, '', '');
    expect($schema)->toBeInstanceOf(ObjectSchema::class);

    expect($schema->properties['arrayProperty']->name)->toBe('arrayProperty');
    expect($schema->properties['arrayProperty']->description)->toBe('Array property');
    expect($schema->properties['arrayProperty']->typeDetails->type)->toBe('array');
})->with('schema_converter_json');

it('creates Schema object from JSON Schema array - collection props', function ($jsonSchema) {
    $schema = (new JsonSchemaToSchema)->fromJsonSchema($jsonSchema, '', '');
    expect($schema)->toBeInstanceOf(ObjectSchema::class);

    expect($schema->properties['collectionProperty']->name)->toBe('collectionProperty');
    expect($schema->properties['collectionProperty']->description)->toBe('Collection property');
    expect($schema->properties['collectionProperty']->typeDetails->type)->toBe('collection');
    expect($schema->properties['collectionProperty']->nestedItemSchema->typeDetails->type)->toBe('object');
    expect($schema->properties['collectionProperty']->nestedItemSchema->typeDetails->class)->toBe('Cognesy\Instructor\Extras\Scalar\Scalar');
    expect($schema->properties['collectionObjectProperty']->name)->toBe('collectionObjectProperty');
    expect($schema->properties['collectionObjectProperty']->description)->toBe('Collection of objects property');
    expect($schema->properties['collectionObjectProperty']->typeDetails->type)->toBe('collection');
    expect($schema->properties['collectionObjectProperty']->nestedItemSchema->typeDetails->type)->toBe('object');
    expect($schema->properties['collectionObjectProperty']->nestedItemSchema->typeDetails->class)->toBe('Tests\Examples\SchemaConverter\TestNestedObject');
    expect($schema->properties['collectionObjectProperty']->nestedItemSchema->properties['nestedStringProperty']->name)->toBe('nestedStringProperty');
    expect($schema->properties['collectionObjectProperty']->nestedItemSchema->properties['nestedStringProperty']->typeDetails->type)->toBe('string');
    expect($schema->properties['collectionEnumProperty']->name)->toBe('collectionEnumProperty');
    expect($schema->properties['collectionEnumProperty']->description)->toBe('Collection of enum property');
    expect($schema->properties['collectionEnumProperty']->typeDetails->type)->toBe('collection');
    expect($schema->properties['collectionEnumProperty']->nestedItemSchema->typeDetails->type)->toBe('enum');
    expect($schema->properties['collectionEnumProperty']->nestedItemSchema->typeDetails->class)->toBe('Tests\Examples\SchemaConverter\TestEnum');
    expect($schema->properties['collectionEnumProperty']->nestedItemSchema->typeDetails->enumType)->toBe('string');
    expect($schema->properties['collectionEnumProperty']->nestedItemSchema->typeDetails->enumValues)->toBe(['one', 'two', 'three']);
})->with('schema_converter_json');

it('throws exception when object schema has empty properties array', function () {
    $jsonSchema = [
        'x-php-class' => 'stdClass',
        'type' => 'object',
        'properties' => [],
    ];
    (new JsonSchemaToSchema)->fromJsonSchema($jsonSchema, '', '');
})->throws(\Exception::class, 'Object must have at least one property');

it('throws exception when array schema is missing items field', function () {
    $jsonSchema = [
        'x-php-class' => 'stdClass',
        'type' => 'object',
        'properties' => [
            'arrayProperty' => [
                'type' => 'array',
            ],
        ],
    ];
    (new JsonSchemaToSchema)->fromJsonSchema($jsonSchema, '', '');
})->throws(\Exception::class, 'Array must have items field');

it('throws exception for invalid enum type', function () {
    $jsonSchema = [
        'x-php-class' => 'stdClass',
        'type' => 'object',
        'properties' => [
            'arrayProperty' => [
                'type' => 'array',
                'items' => [
                    'type' => 'enum', // this is wrong: enum is not a valid type, should be string or int
                    'enum' => ['one', 'two', 'three'],
                    'x-php-class' => 'Tests\Examples\SchemaConverter\TestEnum',
                ],
            ],
        ],
    ];
    (new JsonSchemaToSchema)->fromJsonSchema($jsonSchema, '', '');
})->throws(\Exception::class, 'Nested array type must be either object, string, integer, number or boolean');

it('throws exception when x-php-class is missing for enum', function () {
    $jsonSchema = [
        'x-php-class' => 'stdClass',
        'type' => 'object',
        'properties' => [
            'collectionProperty' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['one', 'two', 'three'],
                ],
            ],
        ],
    ];
    (new JsonSchemaToSchema)->fromJsonSchema($jsonSchema, '', '');
})->throws(\Exception::class, 'Nested enum type needs x-php-class field')->skip('This should be supported by array type');

it('throws exception when x-php-class is missing for object', function () {
    $jsonSchema = [
        'x-php-class' => 'stdClass',
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
    (new JsonSchemaToSchema)->fromJsonSchema($jsonSchema, '', '');
})->throws(\Exception::class, 'Object must have x-php-class field with the target class name');

it('throws exception for invalid type in nested schema', function () {
    $jsonSchema = [
        'x-php-class' => 'stdClass',
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
    (new JsonSchemaToSchema)->fromJsonSchema($jsonSchema, '', '');
})->throws(\Exception::class, 'Nested array type must be either object, string, integer, number or boolean');

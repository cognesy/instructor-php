<?php

declare(strict_types=1);

use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\EnumSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\JsonSchemaParser;
use Cognesy\Schema\TypeInfo;

dataset('schema_converter_json', [[[
    'x-php-class' => 'Cognesy\\Schema\\Tests\\Examples\\SchemaConverter\\TestObject',
    'type' => 'object',
    'properties' => [
        'optionalProperty' => ['description' => 'Optional property', 'type' => 'string'],
        'stringProperty' => ['description' => 'String property', 'type' => 'string'],
        'integerProperty' => ['description' => 'Integer property', 'type' => 'integer'],
        'boolProperty' => ['description' => 'Boolean property', 'type' => 'boolean'],
        'floatProperty' => ['description' => 'Float property', 'type' => 'number'],
        'enumProperty' => [
            'x-php-class' => 'Cognesy\\Schema\\Tests\\Examples\\SchemaConverter\\TestEnum',
            'description' => 'Enum property',
            'type' => 'string',
            'enum' => ['one', 'two', 'three'],
        ],
        'objectProperty' => [
            'x-php-class' => 'Cognesy\\Schema\\Tests\\Examples\\SchemaConverter\\TestNestedObject',
            'description' => 'Object property',
            'type' => 'object',
            'properties' => [
                'nestedStringProperty' => ['type' => 'string'],
                'nestedObjectProperty' => [
                    'x-php-class' => 'Cognesy\\Schema\\Tests\\Examples\\SchemaConverter\\TestDoubleNestedObject',
                    'type' => 'object',
                    'properties' => [
                        'nestedNestedStringProperty' => ['type' => 'string'],
                    ],
                    'required' => ['nestedNestedStringProperty'],
                ],
            ],
            'required' => ['nestedStringProperty', 'nestedObjectProperty'],
        ],
        'arrayProperty' => [
            'description' => 'Array property',
            'type' => 'array',
            'items' => [
                'anyOf' => [
                    ['type' => 'boolean'],
                    ['type' => 'integer'],
                    ['type' => 'number'],
                    ['type' => 'string'],
                    ['type' => 'array'],
                    ['type' => 'object'],
                ],
            ],
        ],
        'stringCollectionProperty' => [
            'description' => 'String collection property',
            'type' => 'array',
            'items' => ['type' => 'string'],
        ],
        'collectionProperty' => [
            'description' => 'Collection property',
            'type' => 'array',
            'items' => [
                'x-php-class' => 'Cognesy\\Schema\\Tests\\Examples\\SchemaConverter\\Simple',
                'type' => 'object',
                'properties' => ['stringProperty' => ['type' => 'string']],
            ],
        ],
        'collectionObjectProperty' => [
            'description' => 'Collection of objects property',
            'type' => 'array',
            'items' => [
                'x-php-class' => 'Cognesy\\Schema\\Tests\\Examples\\SchemaConverter\\TestNestedObject',
                'type' => 'object',
                'properties' => ['nestedStringProperty' => ['type' => 'string']],
                'required' => ['nestedStringProperty'],
            ],
        ],
        'collectionEnumProperty' => [
            'description' => 'Collection of enum property',
            'type' => 'array',
            'items' => [
                'x-php-class' => 'Cognesy\\Schema\\Tests\\Examples\\SchemaConverter\\TestEnum',
                'type' => 'string',
                'enum' => ['one', 'two', 'three'],
            ],
        ],
    ],
    'required' => [
        'stringProperty',
        'integerProperty',
        'boolProperty',
        'floatProperty',
        'enumProperty',
        'objectProperty',
        'arrayProperty',
        'stringCollectionProperty',
        'collectionProperty',
        'collectionObjectProperty',
        'collectionEnumProperty',
    ],
]]]);

it('creates object schema with required fields', function (array $jsonSchema) {
    $schema = (new JsonSchemaParser())->fromJsonSchema($jsonSchema);

    expect($schema)->toBeInstanceOf(ObjectSchema::class);
    expect($schema->required)->toBe([
        'stringProperty',
        'integerProperty',
        'boolProperty',
        'floatProperty',
        'enumProperty',
        'objectProperty',
        'arrayProperty',
        'stringCollectionProperty',
        'collectionProperty',
        'collectionObjectProperty',
        'collectionEnumProperty',
    ]);
})->with('schema_converter_json');

it('maps scalar and enum properties to native TypeInfo-backed schema', function (array $jsonSchema) {
    $schema = (new JsonSchemaParser())->fromJsonSchema($jsonSchema);

    expect(TypeInfo::toJsonType($schema->properties['stringProperty']->type)->toString())->toBe('string');
    expect(TypeInfo::toJsonType($schema->properties['integerProperty']->type)->toString())->toBe('integer');
    expect(TypeInfo::toJsonType($schema->properties['boolProperty']->type)->toString())->toBe('boolean');
    expect(TypeInfo::toJsonType($schema->properties['floatProperty']->type)->toString())->toBe('number');

    $enumProperty = $schema->properties['enumProperty'];
    expect($enumProperty)->toBeInstanceOf(EnumSchema::class);
    expect(TypeInfo::className($enumProperty->type))->toBe('Cognesy\\Schema\\Tests\\Examples\\SchemaConverter\\TestEnum');
    expect($enumProperty->enumValues)->toBe(['one', 'two', 'three']);
})->with('schema_converter_json');

it('maps nested objects and collections with class metadata', function (array $jsonSchema) {
    $schema = (new JsonSchemaParser())->fromJsonSchema($jsonSchema);

    /** @var ObjectSchema $objectProperty */
    $objectProperty = $schema->properties['objectProperty'];
    expect($objectProperty)->toBeInstanceOf(ObjectSchema::class);
    expect(TypeInfo::className($objectProperty->type))->toBe('Cognesy\\Schema\\Tests\\Examples\\SchemaConverter\\TestNestedObject');

    /** @var ObjectSchema $nestedObject */
    $nestedObject = $objectProperty->properties['nestedObjectProperty'];
    expect(TypeInfo::className($nestedObject->type))->toBe('Cognesy\\Schema\\Tests\\Examples\\SchemaConverter\\TestDoubleNestedObject');

    /** @var CollectionSchema $collectionProperty */
    $collectionProperty = $schema->properties['collectionProperty'];
    expect($collectionProperty)->toBeInstanceOf(CollectionSchema::class);
    expect(TypeInfo::className($collectionProperty->nestedItemSchema->type))->toBe('Cognesy\\Schema\\Tests\\Examples\\SchemaConverter\\Simple');

    /** @var CollectionSchema $collectionEnum */
    $collectionEnum = $schema->properties['collectionEnumProperty'];
    expect($collectionEnum->nestedItemSchema)->toBeInstanceOf(EnumSchema::class);
    expect(TypeInfo::className($collectionEnum->nestedItemSchema->type))->toBe('Cognesy\\Schema\\Tests\\Examples\\SchemaConverter\\TestEnum');
    expect($collectionEnum->nestedItemSchema->enumValues)->toBe(['one', 'two', 'three']);
})->with('schema_converter_json');

it('creates object schema with empty properties array', function () {
    $jsonSchema = [
        'x-php-class' => 'stdClass',
        'type' => 'object',
        'properties' => [],
    ];

    $schema = (new JsonSchemaParser())->fromJsonSchema($jsonSchema);
    expect($schema->properties)->toBe([]);
});

it('throws exception for invalid enum type', function () {
    $jsonSchema = [
        'x-php-class' => 'stdClass',
        'type' => 'object',
        'properties' => [
            'arrayProperty' => [
                'type' => 'array',
                'items' => [
                    'type' => 'enum',
                    'enum' => ['one', 'two', 'three'],
                    'x-php-class' => 'Cognesy\\Schema\\Tests\\Examples\\SchemaConverter\\TestEnum',
                ],
            ],
        ],
    ];

    (new JsonSchemaParser())->fromJsonSchema($jsonSchema);
})->throws(Exception::class, 'Invalid JSON type: enum in:');

it('returns array shape schema when x-php-class is missing for nested object', function () {
    $jsonSchema = [
        'x-php-class' => 'stdClass',
        'type' => 'object',
        'properties' => [
            'objectProperty' => [
                'type' => 'object',
                'properties' => [
                    'nestedProperty' => ['type' => 'string'],
                ],
            ],
        ],
    ];

    $schema = (new JsonSchemaParser())->fromJsonSchema($jsonSchema);
    expect($schema->properties['objectProperty'])->toBeInstanceOf(ArrayShapeSchema::class);
});

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

    (new JsonSchemaParser())->fromJsonSchema($jsonSchema);
})->throws(Exception::class, 'Invalid JSON type: invalidType in:');

<?php declare(strict_types=1);

use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Exceptions\SchemaParsingException;
use Cognesy\Schema\JsonSchemaParser;
use Cognesy\Schema\SchemaFactory;

it('preserves untyped nested object as array shape schema with properties', function () {
    $json = [
        'type' => 'object',
        'properties' => [
            'meta' => [
                'type' => 'object',
                'properties' => [
                    'requestId' => ['type' => 'string'],
                    'attempt' => ['type' => 'integer'],
                ],
                'required' => ['requestId'],
            ],
        ],
        'required' => ['meta'],
    ];

    $schema = (new JsonSchemaParser())->fromJsonSchema($json, 'Root', 'Root schema');

    expect($schema)->toBeInstanceOf(ObjectSchema::class);
    expect($schema->properties['meta'])->toBeInstanceOf(ArrayShapeSchema::class);

    /** @var ArrayShapeSchema $meta */
    $meta = $schema->properties['meta'];
    expect(array_keys($meta->properties))->toBe(['requestId', 'attempt']);
    expect($meta->required)->toBe(['requestId']);

    $roundtrip = (new SchemaFactory())->toJsonSchema($schema);
    expect($roundtrip['properties']['meta']['type'] ?? null)->toBe('object');
    expect($roundtrip['properties']['meta']['properties']['requestId']['type'] ?? null)->toBe('string');
    expect($roundtrip['properties']['meta']['properties']['attempt']['type'] ?? null)->toBe('integer');
});

it('keeps core fields stable in json-schema roundtrip for supported object schema', function () {
    $json = [
        'x-php-class' => stdClass::class,
        'type' => 'object',
        'description' => 'Root',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Identifier'],
            'name' => ['type' => 'string'],
            'flags' => [
                'type' => 'array',
                'items' => ['type' => 'boolean'],
            ],
        ],
        'required' => ['id', 'name'],
    ];

    $schema = (new JsonSchemaParser())->fromJsonSchema($json, 'Root', 'Root schema');
    $roundtrip = (new SchemaFactory())->toJsonSchema($schema);

    expect($roundtrip['type'] ?? null)->toBe('object');
    expect($roundtrip['properties']['id']['type'] ?? null)->toBe('integer');
    expect($roundtrip['properties']['name']['type'] ?? null)->toBe('string');
    expect($roundtrip['properties']['flags']['type'] ?? null)->toBe('array');
    expect($roundtrip['required'] ?? [])->toBe(['id', 'name']);
});

it('preserves interface x-php-class metadata in json-schema roundtrip', function () {
    $json = [
        'x-php-class' => CanProvideSchema::class,
        'type' => 'object',
        'description' => 'Root',
        'properties' => [
            'id' => ['type' => 'integer'],
        ],
        'required' => ['id'],
    ];

    $schema = (new JsonSchemaParser())->fromJsonSchema($json, 'Root', 'Root schema');
    $roundtrip = (new SchemaFactory())->toJsonSchema($schema);

    expect($roundtrip['x-php-class'] ?? null)->toBe(CanProvideSchema::class);
});

it('throws domain parsing exception for non-object root schema', function () {
    expect(fn() => (new JsonSchemaParser())->fromJsonSchema(['type' => 'string']))
        ->toThrow(SchemaParsingException::class, 'Root JSON Schema must be an object');
});
